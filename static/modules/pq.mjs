//Core by ZERONETA
//不要压缩先观察一段时间
;export default ((global, undefined)=>
{
	const//JavaScript 标准库 let's go ES6
	{Array, BigInt, DataView, Date, JSON, Map, Number, Object, Promise, Reflect, RegExp, Set, Symbol, String} = global,
	{parseInt, parseFloat} = Number,
	fromCodePoint = String.fromCodePoint,
	pq = Object.getOwnPropertyNames(global.Math).reduce((p, q)=> [p[q === 'random' ? 'lcg_value' : q] = global.Math[q], p][1], class
	{
		static Infinity = global.Infinity;
		static NaN = global.NaN;
		static encoding = 'utf8';
		static version = 3.1;
		constructor(extend)
		{
			return extend.call(this, global);
		}
	});

	class struct extends global.Uint8Array
	{
		static latin1 = (data)=> struct.from(data, (byte)=> byte.codePointAt(0));
		static hex = (data)=> struct.from(data.match(/.{2}/g), (hex)=> parseInt(hex, 16));
		static utf8 = (data)=>
		{
			const buffer = [];
			for (let unicode of data)
			{
				const value = unicode.codePointAt(0);
				value < 128
					? buffer[buffer.length] = value
					: buffer.push(...(value < 2048
						? [value >> 6 | 192, value & 63 | 128]
						: [value >> 12 | 224, value >> 6 & 63 | 128, value & 63 | 128]));
			}
			return struct.from(buffer);
		};
		// constructor(...params)
		// {
		// 	super(...params).view = new DataView(this.buffer);
		// }
		// uint8(offset) {return this[offset] & 0xff;}
		// int8(offset) {return this.uint8(offset) >> 7 ? 0x7f - this.uint8(offset) : this.uint8(offset) & 0x7f;}
		// uint16(offset) {return (this.uint8(offset) << 8 | this.uint8(offset + 1)) >>> 0;}
		// int16(offset) {return this.uint16(offset) >> 15 ? 0x7fff - this.uint16(offset) : this.uint16(offset) & 0x7fff;}
		// uint32(offset) {return (this.uint16(offset) << 16 | this.uint16(offset + 2)) >>> 0;}
		// int32(offset) {return this.uint32(offset) >> 31 ? 0x7fffffff - this.uint32(offset) : this.uint32(offset) & 0x7fffffff;}
		get view()
		{
			return new DataView(this.buffer);
		}
		get latin1()
		{
			return fromCodePoint(...this);
		}
		get hex()
		{
			return Array.from(this, (byte)=> byte.toString(16).padStart(2, 0)).join('');
		}
		get utf8()
		{
			const buffer = [], length = this.length;
			for (let i = 0; i < length;)
			{
				buffer[buffer.length] = this[i] < 128
					? this[i++]
					: (this[i + 1] > 127
						? (this[i++] & 15) << 12 | (this[i++] & 63) << 6
						: (this[i++] & 31) << 6) | this[i++] & 63;
			}
			return fromCodePoint(...buffer);
		}
	}

	class datetime extends Date
	{
		[Symbol.toStringTag] = 'DateTime';
		static ATOM = 'Y-m-d\\TH:i:sP';
		static COOKIE = 'l, d-M-Y H:i:s T';
		static ISO8601 = 'Y-m-d\\TH:i:sO';
		static RFC822 = 'D, d M y H:i:s O';
		static RFC850 = 'l, d-M-y H:i:s T';
		static RFC1036 = 'D, d M y H:i:s O';
		static RFC1123 = 'D, d M Y H:i:s O';
		static RFC2822 = 'D, d M Y H:i:s O';
		static RFC3339 = 'Y-m-d\\TH:i:sP';
		static RFC3339_EXTENDED = 'Y-m-d\\TH:i:s.vP';
		static RSS = 'D, d M Y H:i:s O';
		static W3C = 'Y-m-d\\TH:i:sP';
		constructor(time)
		{
			time === undefined ? super() : super(time * 1000 || time);
		}
		format(format)
		{
			return format.replace(/\\?([a-z])/ig, (type, word)=> datetime.prototype.hasOwnProperty(type) ? this[type] : word);
		}
		//Day
		get d() {return String(this.j).padStart(2, 0);}//月份中的第几天，有前导零的 2 位数字
		get D() {return this.l.substring(0, 3);}//星期中的第几天，文本表示，3 个字母
		get j() {return this.getDate();}//月份中的第几天，没有前导零
		get l() {return `${['Sun', 'Mon', 'Tues', 'Wednes', 'Thurs', 'Fri', 'Satur'][this.w]}day`;}//星期几，完整的文本格式
		get N() {return this.w || 7;}//ISO-8601 格式数字表示的星期中的第几天
		get S() {return ['th st nd rd'][parseInt(this.j / 10, 10) === 1 ? 0 : this.j % 10] || 'th';}//每月天数后面的英文后缀，2 个字符
		get w() {return this.getDay();}//星期中的第几天，数字表示
		get z() {return pq.round(Date.UTC(this.Y, this.n - 1, this.j) - Date.UTC(this.Y, 0, 1)) / 864e5;}//年份中的第几天
		//Week
		get W() {return String(pq.round((Date.UTC(this.Y, this.n - 1, this.j - this.N + 3) - Date.UTC(this.Y, 0, 4)) / 864e5 / 7) + 1).padStart(2, 0);}//ISO-8601 格式年份中的第几周，每周从星期一开始
		//Month
		get F() {return ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][this.n - 1];}//月份，完整的文本格式，例如 January 或者 December
		get m() {return String(this.n).padStart(2, 0);}//数字表示的月份，有前导零
		get M() {return this.F.substring(0, 3);}//三个字母缩写表示的月份
		get n() {return this.getMonth() + 1;}//数字表示的月份，没有前导零
		get t() {return new Date(this.Y, this.n, 0).getDate();}//给定月份所应有的天数
		//Year
		get L() {return Number(new Date(this.Y, 1, 29).getMonth() === 1);}//是否为闰年，如果是闰年为 1，否则为 0
		get o() {return this.Y + (this.n === 12 && this.W < 9 ? -1 : this.n === 1 && this.W > 9);}//ISO-8601 格式年份数字，这和 Y 的值相同，只除了如果 ISO 的星期数（W）属于前一年或下一年，则用那一年
		get y() {return String(this.Y).slice(-2);}//2 位数字表示的年份
		get Y() {return this.getFullYear();}//4 位数字完整表示的年份
		//Time
		get a() {return this.G > 11 ? 'pm' : 'am';}//小写的上午和下午值
		get A() {return this.a.toUpperCase();}//大写的上午和下午值
		get B() {return String(pq.floor((this.getUTCHours() * 36e2 + this.getUTCMinutes() * 60 + this.getUTCSeconds() + 36e2) / 86.4) % 1e3).padStart(3, 0);}//Swatch Internet 标准时
		get g() {return this.G % 12 || 12;}//小时，12 小时格式，没有前导零
		get G() {return this.getHours();}//小时，24 小时格式，没有前导零
		get h() {return String(this.g).padStart(2, 0);}//小时，12 小时格式，有前导零
		get H() {return String(this.G).padStart(2, 0);}//小时，24 小时格式，有前导零
		get i() {return String(this.getMinutes()).padStart(2, 0);}//分钟，有前导零
		get s() {return String(this.getSeconds()).padStart(2, 0);}//秒数，有前导零
		get u() {return String(this.getMilliseconds() * 1000).padStart(6, 0);}//微秒，有前导零
		//Timezone
		get e() {return 'UTC'}//时区标识，这个函数还不完整
		get I() {return 0}//是否为夏令时，如果是夏令时为 1，否则为 0
		get O() {return `${this.getTimezoneOffset() > 0 ? '-' : '+'}${String(pq.floor(pq.abs(this.getTimezoneOffset()) / 60) * 100 + pq.abs(this.getTimezoneOffset()) % 60).padStart(4, 0)}`;}//与格林威治时间相差的小时数
		get P() {return `${this.O.substring(0, 3)}:${this.O.substring(3, 5)}`;}//与格林威治时间（GMT）的差别，小时和分钟之间有冒号分隔
		get T() {return 'CST'}//本机所在的时区，这个函数还不完整
		get Z() {return this.getTimezoneOffset() * 60;}//时差偏移量的秒数，UTC 西边的时区偏移量总是负的，UTC 东边的时区偏移量总是正的
		//Full Date/Time
		get c() {return this.format(datetime.ISO8601);}//ISO 8601 格式的日期，例如：2004-02-12T15:19:21+00:00
		get r() {return this.format(datetime.RFC2822);}//RFC 2822 格式的日期，例如：Thu, 21 Dec 2000 16:01:07 +0200
		get U() {return parseInt(this * 0.001, 10);}//从 Unix 纪元（January 1 1970 00:00:00 GMT）开始至今的秒数
	}

	// class mapping extends Map
	// {
	// 	constructor(initialize)
	// 	{
	// 		super().initialize = initialize;
	// 	}
	// 	push(object)
	// 	{
	// 		this.set(object, object = this.initialize(object));
	// 		return object;
	// 	}
	// 	pull(object)
	// 	{
	// 		return this.has(object) ? this.get(object) : this.push(object);
	// 	}
	// }

	class base64
	{
		static code = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/==';
		static decode = (data)=>
		{
			const buffer = [], length = data.length;
			for (let i = 0, a1, a2, a3, a4, value; i < length;)
			{
				a1 = base64.code.indexOf(data.charAt(i++)),
				a2 = base64.code.indexOf(data.charAt(i++)),
				a3 = base64.code.indexOf(data.charAt(i++)),
				a4 = base64.code.indexOf(data.charAt(i++)),
				value = a1 << 18 | a2 << 12 | a3 << 6 | a4,
				a3 > 63
					? buffer[buffer.length] = value >> 16 & 255
					: buffer.push(...(a4 > 63
						? [value >> 16 & 255, value >> 8 & 255]
						: [value >> 16 & 255, value >> 8 & 255, value & 255]));
			}
			return new struct(buffer)[pq.encoding];
		};
		static encode = (data)=>
		{
			const buffer = [], bytecode = struct[pq.encoding](data), length = bytecode.length, append = 3 - length % 3;
			for (let i = 0, n; i < length;)
			{
				n = bytecode[i++] << 16 | bytecode[i++] << 8 | bytecode[i++],
				buffer.push(
					base64.code.charAt(n >> 18 & 63),
					base64.code.charAt(n >> 12 & 63),
					base64.code.charAt(n >> 6 & 63),
					base64.code.charAt(n & 63));
			}
			if (append && append !== 3)
			{
				buffer.splice(-append, append, base64.code.slice(-append));
			}
			return buffer.join('');
		};
	}

	class sha3
	{
		static data = [
			0x0000000000000001n, 0x0000000000008082n, 0x800000000000808an,
			0x8000000080008000n, 0x000000000000808bn, 0x0000000080000001n,
			0x8000000080008081n, 0x8000000000008009n, 0x000000000000008an,
			0x0000000000000088n, 0x0000000080008009n, 0x000000008000000an,
			0x000000008000808bn, 0x800000000000008bn, 0x8000000000008089n,
			0x8000000000008003n, 0x8000000000008002n, 0x8000000000000080n,
			0x000000000000800an, 0x800000008000000an, 0x8000000080008081n,
			0x8000000000008080n, 0x0000000080000001n, 0x8000000080008008n];
		static rotl = (uint64, offset)=> (uint64 << offset & 0xffffffffffffffffn) | (uint64 >> (64n - offset));
		static keccak1600 = (r, c, m)=>
		{
			const
				state = Array.from(Array(5), ()=> Array(5).fill(0n)),
				bytecode = [...struct[pq.encoding](m)],
				padlength = (r / 8) - bytecode.length % (r / 8),
				bitlength = r / 64,
				blocksize = bitlength * 8;
			if (padlength === 1)
			{
				bytecode[bytecode.length] = 0x86;
			}
			else
			{
				bytecode.push(0x06, ...Array(padlength - 2).fill(0), 0x80)
			}
			for (let i = 0; i < bytecode.length; i += blocksize)
			{
				for (let n = 0; n < bitlength; ++n)
				{
					state[n % 5][pq.floor(n / 5)] ^=
						(BigInt(bytecode[i + n * 8 + 0]) << 0n) +
						(BigInt(bytecode[i + n * 8 + 1]) << 8n) +
						(BigInt(bytecode[i + n * 8 + 2]) << 16n) +
						(BigInt(bytecode[i + n * 8 + 3]) << 24n) +
						(BigInt(bytecode[i + n * 8 + 4]) << 32n) +
						(BigInt(bytecode[i + n * 8 + 5]) << 40n) +
						(BigInt(bytecode[i + n * 8 + 6]) << 48n) +
						(BigInt(bytecode[i + n * 8 + 7]) << 56n);
				}
				for (let n = 0; n < 24; ++n)
				{
					const c = [], d = [];
					for (let x = 0; x < 5; ++x)
					{
						c[x] = state[x][0];
						for (let y = 1; y < 5; ++y)
						{
							c[x] ^= state[x][y];
						}
					}
					for (let x = 0; x < 5; ++x)
					{
						d[x] = c[(x + 4) % 5] ^ sha3.rotl(c[(x + 1) % 5], 1n);
						for (let y = 0; y < 5; ++y)
						{
							state[x][y] = state[x][y] ^ d[x];
						}
					}
					let [x, y] = [1, 0], cur = state[x][y];
					for (let t = 0; t < 24; ++t)
					{
						const [X, Y] = [y, (2 * x + 3 * y) % 5], tmp = state[X][Y];
						state[X][Y] = sha3.rotl(cur, BigInt((t + 1) * (t + 2) / 2) % 64n);
						cur = tmp, [x, y] = [X, Y];
					}
					for (let y = 0; y < 5; ++y)
					{
						const c = [];
						for (let x = 0; x < 5; ++x)
						{
							c[x] = state[x][y];
						}
						for (let x = 0; x < 5; ++x)
						{
							state[x][y] ^= ~c[(x + 1) % 5] & c[(x + 2) % 5];
						}
					}
					state[0][0] ^= sha3.data[n];
				}
			}
			return struct.from(state.reduce((...[result, , column])=>
			{
				state.forEach((row)=> result.push(
					Number(row[column] >> 0n & 255n),
					Number(row[column] >> 8n & 255n),
					Number(row[column] >> 16n & 255n),
					Number(row[column] >> 24n & 255n),
					Number(row[column] >> 32n & 255n),
					Number(row[column] >> 40n & 255n),
					Number(row[column] >> 48n & 255n),
					Number(row[column] >> 56n & 255n)));
				return result;
			}, []).slice(0, c / 8));
		};
	}

	class md5
	{
		static key = [[7, 12, 17, 22], [5, 9, 14, 20], [4, 11, 16, 23], [6, 10, 15, 21]];
		static app = [(x, y, z)=> x & y | ~x & z, (x, y, z)=> x & z | y & ~z, (x, y, z)=> x ^ y ^ z, (x, y, z)=> y ^ (x | ~z)];
		static get = [(x)=> x, (x)=> (5 * x + 1) % 16, (x)=> (3 * x + 5) % 16, (x)=> (7 * x) % 16];
		static map = Array.from(Array(64), (...[, index])=> parseInt(pq.abs(pq.sin(index + 1) * pq.pow(2, 32)), 10));
		static rotl = (x, y)=> x << y | x >>> 32 - y;
		static hash = (data)=>
		{
			const
				state = [0x67452301, 0xefcdab89, 0x98badcfe, 0x10325476],
				bytecode = [...struct[pq.encoding](data)],
				modlength = bytecode.length % 64;
			bytecode.push(0x80, ...Array((modlength < 56 ? 56 - modlength : 120 - modlength) - 1).fill(0),
				...(bytecode.length * 8).toString(16).padStart(16, 0).match(/.{2}/g).reverse().map((hex)=> parseInt(hex, 16)));
			for (let i = 0; i < bytecode.length; i += 64)
			{
				let swap = state.slice(0);
				for (let n = 0; n < 64; ++n)
				{
					const x = pq.floor(n / 16), offset = md5.get[x](n) * 4 + i, y = bytecode.slice(offset, offset + 4);
					swap.splice(0, 4, swap[3], swap[1] + md5.rotl(swap[0] + md5.app[x % 4](swap[1], swap[2], swap[3])
						+ (y[3] << 24 | y[2] << 16 | y[1] << 8 | y[0]) + md5.map[n],
						md5.key[x % 4][n % 4]), swap[1], swap[2]);
				}
				state.splice(0, 4, swap[0] + state[0], swap[1] + state[1], swap[2] + state[2], swap[3] + state[3]);
			}
			return new struct(Array.from(Array(16), (...[, index])=> state[index >> 2] >> index % 4 * 8 & 255));
		};
	}

	return Object.assign(pq,
	{
		struct,
		datetime,
		// mapping:			(initialize)=> new mapping(initialize),
		promise:			(waiting)=> new Promise(waiting),
		hsl:				(hue, saturation = 1, lightness = 0.5)=>
		{
			const
				chroma = (1 - pq.abs((2 * lightness) - 1)) * saturation,
				prime = hue / 60,
				second = chroma * (1 - pq.abs((prime % 2) - 1)),
				adjustment = lightness - (chroma / 2);
			let colors = [];
			switch (pq.floor(prime))
			{
				case 5: [...colors] = [chroma, 0, second]; break;
				case 4: [...colors] = [second, 0, chroma]; break;
				case 3: [...colors] = [0, second, chroma]; break;
				case 2: [...colors] = [0, chroma, second]; break;
				case 1: [...colors] = [second, chroma, 0]; break;
				default:[...colors] = [chroma, second, 0];
			}
			return colors.map((color)=> pq.round((color + adjustment) * 255) & 255);
		},
		//PHP Variable handling/Classes/Objects
		gettype:			(object)=> Object.prototype.toString.call(object).slice(8, -1),
		is_a:				(object, extend)=> object instanceof extend,
		is_array:			Array.isArray,
		is_bool:			(object)=> typeof object === 'boolean',
		is_defined:			(object)=> pq.is_void(object) === false && pq.is_null(object) === false,
		is_entries:			(object)=> pq.is_defined(object) && pq.is_scalar(object) === false,//这段代码非常复杂并且伪装的很好应该没人可以看懂其中的意思
		is_finite:			Number.isFinite,
		is_float:			(number)=> +number === number && !!(number % 1),
		is_function:		(object)=> typeof object === 'function',
		is_int:				Number.isInteger,
		is_iterable:		(object)=> pq.is_entries(object) && pq.is_function(object[Symbol.iterator]),
		is_nan:				Number.isNaN,
		is_null:			(object)=> object === null,
		is_numeric:			(object)=> typeof object === 'number',
		is_object:			(object)=> pq.is_defined(object) && Object.getPrototypeOf(object) === Object.prototype,
		is_regexp:			(object)=> pq.gettype(object) === 'RegExp',
		is_scalar:			(object)=> ['boolean', 'number', 'string'].includes(typeof object),
		is_string:			(object)=> typeof object === 'string',
		is_void:			(object)=> object === undefined,
		boolval:			global.Boolean,
		delete:				(object, property)=> Reflect.deleteProperty(object, property),
		empty:				(object)=> pq.is_entries(object) ? (pq.is_iterable(object) ? object : Object.entries(object))[Symbol.iterator]().next().done : !object,
		floatval:			(number)=> parseFloat(number) || 0,
		intval:				(number, base = 10)=> parseInt(number, base) || 0,
		isset:				(...params)=> params.every(pq.is_defined),
		//strval:				(object)=> object.toString(),
		method_exists:		(object, method)=> pq.is_defined(object) && pq.is_function(object[method]),
		property_exists:	(object, property)=> pq.is_defined(object) && Object.prototype.hasOwnProperty.call(object, property),
		//defval:				(def, val = def)=> val,
		//PHP XML Parser
		utf8_decode:		(data)=> struct.latin1(data).utf8,
		utf8_encode:		(data)=> struct.utf8(data).latin1,
		//PHP URLs
		base64_decode:		base64.decode,
		base64_encode:		base64.encode,
		http_build_query:	(data, prefix = '', separator = '&')=>
		{
			const merge = [];
			Object.keys(data).forEach((name)=>
			{
				merge[merge.length] = pq.is_entries(data[name])
					? pq.http_build_query(data[name], name, separator)
					: `${pq.urlencode(prefix.length ? `${prefix}[${name}]` : name)}=${pq.urlencode(data[name])}`;
			});
			return merge.join(separator)
		},
		urldecode:			(data)=> global.decodeURIComponent(data.replace(/\+/g, ' ')),
		urlencode:			(data)=> global.encodeURIComponent(data).replace(/%20|[\!'\(\)\*\+\/@~]/g, (escape)=> ({'%20': '+', '!': '%21', "'": '%27', '(': '%28', ')': '%29', '*': '%2A', '+': '%2B', '/': '%2F', '@': '%40', '~': '%7E'}[escape])),
		//PHP Strings
		addslashes:			(data)=> String(data).replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0'),
		bin2hex:			(data)=> struct.latin1(data).hex,
		chr:				(code)=> fromCodePoint(code),
		chunk_split:		(body, chunklen = 76, end = '\r\n')=> body.match(RegExp(`.{0,${chunklen}}`, 'g')).join(end),
		hex2bin:			(data)=> struct.hex(data).latin1,
		ltrim:				(data)=> String(data).trimStart(),
		md5:				(data, raw = false)=> md5.hash(data)[raw ? 'latin1' : 'hex'],
		ord:				(word)=> String(word).codePointAt(0),
		rtrim:				(data)=> String(data).trimEnd(),
		str_shuffle:		(data)=> pq.shuffle([...String(data)]).join(''),
		str_split:			(data, length = 1)=> length === 1 ? [...String(data)] : String(data).match(RegExp(`.{${length}}|.+`, 'g')),
		strlen:				(data)=> struct[pq.encoding](data).latin1.length,
		strrev:				(data)=> [...String(data)].reverse().join(''),
		strtolower:			(data)=> data.toLowerCase(),
		strtoupper:			(data)=> data.toUpperCase(),
		trim:				(data)=> String(data).trim(),
		ucfirst:			(data)=> /^[a-z]/.test(data) ? data.charAt(0).toUpperCase() + data.substring(1) : data,
		ucwords:			(data, delimiters = ' \t\r\n\f\v')=> data.split(RegExp(`([${delimiters}]+)`)).map(pq.ucfirst).join(''),
		//PHP PCRE
		preg_quote:			(data, delimiter = '')=> String(data).replace(RegExp(`[.\\\\+*?\\[\\^\\]$(){}=!<>|:\\${delimiter}-]`, 'g'), '\\$&'),
		//PHP Misc
		// pq.pack =				(format, ...params)=>
		// pq.uniqid =				(prefix, entropy = false)=>
		// pq.unpack =				(format , data, offset = 0)=>
		//PHP Math
		base_convert:		(number , from , to)=> parseInt(number, from).toString(to),
		bindec:				(binary)=> parseInt(binary, 2),
		decbin:				(number)=> parseInt(number >>> 0, 10).toString(2),
		dechex:				(number)=> parseInt(number >>> 0, 10).toString(16),
		decoct:				(number)=> parseInt(number >>> 0, 10).toString(8),
		deg2rad:			(number)=> number / 180 * pq.PI,
		hexdec:				(hex)=> parseInt(hex, 16),
		octdec:				(octal)=> parseInt(octal, 8),
		rad2deg:			(number)=> number / pq.PI * 180,
		rand:				(min = 0, max = 0x7fffffff)=> parseInt(pq.lcg_value() * (max - min + 1), 10) + min,
		//PHP JSON
		json_decode:		(data)=> {try {return JSON.parse(data);} catch {}},
		json_encode:		(data)=> JSON.stringify(data),
		//PHP Date/Time
		date:				(format, timestamp)=> new datetime(timestamp).format(format),
		date_create:		(time)=> new datetime(time),
		mktime:				(...params)=>
		{
			const time = new Date;
			switch (params.length)
			{
				case 6: time.setFullYear(params[5]);
				case 5: time.setDate(params[4]);
				case 4: time.setMonth(params[3] - 1);
				case 3: time.setSeconds(params[2]);
				case 2: time.setMinutes(params[1]);
				case 1: time.setHours(params[0]);
			}
			return parseInt(time * 0.001, 10);
		},
		strtotime:			(time)=> parseInt(Date.parse(time) * 0.001, 10),
		time:				()=> parseInt(Date.now() * 0.001, 10),
		//PHP Arrays
		array_column:		(object, field, key = null)=> key === null
			? (pq.is_array(object) ? object : Object.values(object)).map((item)=> item[field])
			: (pq.is_array(object) ? object : Object.values(object)).reduce((data, item)=> [data[item[key]] = item[field], data][1], {}),
		array_count_values:	(object)=> Object.values(object).reduce((count, value)=>
		{
			if (count.hasOwnProperty(value))
			{
				++count[value];
			}
			else
			{
				count[value] = 1;
			}
			return count;
		}, {}),
		array_keys:			Object.keys,
		array_unique:		(object)=> Array.from(new Set(Object.values(object))),
		array_values:		Object.values,
		in_array:			(needle , haystack, strict = false)=>
		{
			//? Array.prototype.includes.call(haystack, needle)
			const values = Object.values(haystack);
			return strict ? values.includes(needle) : values.some((value)=> value == needle);
		},
		shuffle:			(array)=>
		{
			const max = array.length - 1;
			for (let i = 0, random; i <= max; ++i)
			{
				random = pq.rand(0, max);
				[array[i], array[random]] = [array[random], array[i]];
			}
			return array;
		},
		//PHP Hash
		hash:				Object.assign((algo, data, raw = false)=> pq.hash[algo](data)[raw ? 'latin1' : 'hex'],
		{
			'sha3-224':		(data)=> sha3.keccak1600(1152, 224, data),
			'sha3-256':		(data)=> sha3.keccak1600(1088, 256, data),
			'sha3-384':		(data)=> sha3.keccak1600(832, 384, data),
			'sha3-512':		(data)=> sha3.keccak1600(576, 512, data),
			md5:			md5.hash
		}),
		hash_algos:			()=> pq.array_keys(pq.hash)
	});
})(globalThis);