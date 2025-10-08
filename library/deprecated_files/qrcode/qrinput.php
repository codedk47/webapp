<?php
class QRinput {

    public $items;

    private $version;
    private $level;

    //----------------------------------------------------------------------
    public function __construct($version = 0, $level = QR_ECLEVEL_L)
    {
        if ($version < 0 || $version > QRSPEC_VERSION_MAX || $level > QR_ECLEVEL_H) {
            throw new Exception('Invalid version no');
            return NULL;
        }
        
        $this->version = $version;
        $this->level = $level;
    }

    //----------------------------------------------------------------------
    public function getVersion()
    {
        return $this->version;
    }

    //----------------------------------------------------------------------
    public function setVersion($version)
    {
        if($version < 0 || $version > QRSPEC_VERSION_MAX) {
            throw new Exception('Invalid version no');
            return -1;
        }

        $this->version = $version;

        return 0;
    }

    //----------------------------------------------------------------------
    public function getErrorCorrectionLevel()
    {
        return $this->level;
    }

    //----------------------------------------------------------------------
    public function append($mode, $size, $data)
    {
        $this->items[] = new QRinputItem($mode, $size, $data);
    }


    //----------------------------------------------------------------------
    public static function estimateBitsMode8($size)
    {
        return $size * 8;
    }

    //----------------------------------------------------------------------
    public function estimateBitStreamSize($version)
    {
        $bits = 0;

        foreach($this->items as $item) {
            $bits += $item->estimateBitStreamSizeOfEntry($version);
        }

        return $bits;
    }

    //----------------------------------------------------------------------
    public function estimateVersion()
    {
        $version = 0;
        $prev = 0;
        do {
            $prev = $version;
            $bits = $this->estimateBitStreamSize($prev);
            $version = QRspec::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
            if ($version < 0) {
                return -1;
            }
        } while ($version > $prev);

        return $version;
    }

    //----------------------------------------------------------------------
    public static function lengthOfCode($mode, $version, $bits)
    {
        $payload = $bits - 4 - QRspec::lengthIndicator($mode, $version);
        $size = (int)($payload / 8);

        
        $maxsize = QRspec::maximumWords($mode, $version);
        if($size < 0) $size = 0;
        if($size > $maxsize) $size = $maxsize;

        return $size;
    }

    //----------------------------------------------------------------------
    public function createBitStream()
    {
        $total = 0;

        foreach($this->items as $item) {
            $bits = $item->encodeBitStream($this->version);
            
            if($bits < 0) 
                return -1;
                
            $total += $bits;
        }

        return $total;
    }

    //----------------------------------------------------------------------
    public function convertData()
    {
        $ver = $this->estimateVersion();
        if($ver > $this->getVersion()) {
            $this->setVersion($ver);
        }

        for(;;) {
            $bits = $this->createBitStream();
            
            if($bits < 0) 
                return -1;
                
            $ver = QRspec::getMinimumVersion((int)(($bits + 7) / 8), $this->level);
            if($ver < 0) {
                throw new Exception('WRONG VERSION');
                return -1;
            } else if($ver > $this->getVersion()) {
                $this->setVersion($ver);
            } else {
                break;
            }
        }

        return 0;
    }

    //----------------------------------------------------------------------
    public function appendPaddingBit(&$bstream)
    {
        $bits = $bstream->size();
        $maxwords = QRspec::getDataLength($this->version, $this->level);
        $maxbits = $maxwords * 8;

        if ($maxbits == $bits) {
            return 0;
        }

        if ($maxbits - $bits < 5) {
            return $bstream->appendNum($maxbits - $bits, 0);
        }

        $bits += 4;
        $words = (int)(($bits + 7) / 8);

        $padding = new QRbitstream();
        $ret = $padding->appendNum($words * 8 - $bits + 4, 0);
        
        if($ret < 0) 
            return $ret;

        $padlen = $maxwords - $words;
        
        if($padlen > 0) {
            
            $padbuf = array();
            for($i=0; $i<$padlen; $i++) {
                $padbuf[$i] = ($i&1)?0x11:0xec;
            }
            
            $ret = $padding->appendBytes($padlen, $padbuf);
            
            if($ret < 0)
                return $ret;
            
        }

        $ret = $bstream->append($padding);
        
        return $ret;
    }

    //----------------------------------------------------------------------
    public function mergeBitStream()
    {
        if($this->convertData() < 0) {
            return null;
        }

        $bstream = new QRbitstream();
        
        foreach($this->items as $item) {
            $ret = $bstream->append($item->bstream);
            if($ret < 0) {
                return null;
            }
        }

        return $bstream;
    }

    //----------------------------------------------------------------------
    public function getBitStream()
    {

        $bstream = $this->mergeBitStream();
        
        if($bstream == null) {
            return null;
        }
        
        $ret = $this->appendPaddingBit($bstream);
        if($ret < 0) {
            return null;
        }

        return $bstream;
    }

    //----------------------------------------------------------------------
    public function getByteStream()
    {
        $bstream = $this->getBitStream();
        if($bstream == null) {
            return null;
        }
        
        return $bstream->toByte();
    }
}