<?php
class QRinputItem {
    
    public $mode;
    public $size;
    public $data;
    public $bstream;

    public function __construct($mode, $size, $data, $bstream = null) 
    {
        $this->mode = $mode;
        $this->size = $size;
        $this->data = $data;
        $this->bstream = $bstream;
    }

    //----------------------------------------------------------------------
    public function encodeMode8($version)
    {
        try {
            $bs = new QRbitstream();

            $bs->appendNum(4, 0x4);
            $bs->appendNum(QRspec::lengthIndicator(QR_MODE_8, $version), $this->size);

            for($i=0; $i<$this->size; $i++) {
                $bs->appendNum(8, ord($this->data[$i]));
            }

            $this->bstream = $bs;
            return 0;
        
        } catch (Exception $e) {
            return -1;
        }
    }

    //----------------------------------------------------------------------
    public function encodeModeStructure()
    {
        try {
            $bs =  new QRbitstream();
            
            $bs->appendNum(4, 0x03);
            $bs->appendNum(4, ord($this->data[1]) - 1);
            $bs->appendNum(4, ord($this->data[0]) - 1);
            $bs->appendNum(8, ord($this->data[2]));

            $this->bstream = $bs;
            return 0;
        
        } catch (Exception $e) {
            return -1;
        }
    }
    
    //----------------------------------------------------------------------
    public function estimateBitStreamSizeOfEntry($version)
    {
        $bits = 0;

        if($version == 0) 
            $version = 1;
        $bits = QRinput::estimateBitsMode8($this->size);


        $l = QRspec::lengthIndicator($this->mode, $version);
        $m = 1 << $l;
        $num = (int)(($this->size + $m - 1) / $m);

        $bits += $num * (4 + $l);

        return $bits;
    }
    
    //----------------------------------------------------------------------
    public function encodeBitStream($version)
    {
        try {
        
            unset($this->bstream);
            $words = QRspec::maximumWords($this->mode, $version);
            
            if($this->size > $words) {
            
                $st1 = new QRinputItem($this->mode, $words, $this->data);
                $st2 = new QRinputItem($this->mode, $this->size - $words, array_slice($this->data, $words));

                $st1->encodeBitStream($version);
                $st2->encodeBitStream($version);
                
                $this->bstream = new QRbitstream();
                $this->bstream->append($st1->bstream);
                $this->bstream->append($st2->bstream);
                
                unset($st1);
                unset($st2);
                
            } else {
                $ret = $this->encodeMode8($version);
                if($ret < 0)
                    return -1;
            }

            return $this->bstream->size();
        
        } catch (Exception $e) {
            return -1;
        }
    }
};