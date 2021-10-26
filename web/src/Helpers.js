// ##########################################
// Global Requires
// ##########################################

// lodash
export var _ = require('lodash');

// TODO: Replace Locutus stuff with lodash most of it should work out nicely and if not i need to write a wrapper.
// var
export var empty = require('locutus/php/var/empty');
export var isset = require('locutus/php/var/isset');

// url
export var base64_decode = require('locutus/php/url/base64_decode');
export var base64_encode = require('locutus/php/url/base64_encode');
export var urlencode = require('locutus/php/url/urlencode');

// array
export var in_array = require('locutus/php/array/in_array');
export var array_slice = require('locutus/php/array/array_slice');
export var array_search = require('locutus/php/array/array_search');
export var count = require('locutus/php/array/count');
export var array_filter = require('locutus/php/array/array_filter');

// strings
export var explode = require('locutus/php/strings/explode');
export var str_replace = require('locutus/php/strings/str_replace');
export var strtolower = require('locutus/php/strings/strtolower');
export var strtoupper = require('locutus/php/strings/strtoupper');
export var trim = require('locutus/php/strings/trim');
export var nl2br = require('locutus/php/strings/nl2br');

// math
export var round = require('locutus/php/math/round');
export var abs = require('locutus/php/math/abs');

// time
export var time = require('locutus/php/datetime/time');
export var date = require('locutus/php/datetime/date');
export var gmdate = require('locutus/php/datetime/gmdate');
export var strtotime = require('locutus/php/datetime/strtotime');

// filesystem
export var basename = require('locutus/php/filesystem/basename');
export var pathinfo = require('locutus/php/filesystem/pathinfo');

// misc
export var uniqid = require('locutus/php/misc/uniqid');