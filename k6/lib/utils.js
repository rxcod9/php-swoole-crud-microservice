/**
 * @file lib/utils.js
 * @description Contains shared helper functions for entity generation and randomization.
 */

import http from 'k6/http';
import { check } from 'k6';

/**
 * Securely generate a uniform random integer between min (inclusive) and max (exclusive).
 * Uses rejection sampling to avoid modulo bias.
 *
 * @param {number} min - Minimum integer (inclusive)
 * @param {number} max - Maximum integer (exclusive)
 * @returns {number} A cryptographically secure random integer
 * @throws {Error} If invalid range
 */
export function secureRandomInt(min, max) {
    if (!Number.isInteger(min) || !Number.isInteger(max)) {
        throw new TypeError('Both min and max must be integers.');
    }
    if (max <= min) {
        throw new RangeError(`Invalid range: max (${max}) must be greater than min (${min}).`);
    }

    const range = max - min;
    if (range > 0xffffffff) {
        throw new RangeError('Range too large; must be less than 2^32.');
    }

    const array = new Uint32Array(1);
    const maxUnbiased = Math.floor(0xffffffff / range) * range;

    while (true) {
        crypto.getRandomValues(array);
        const random32 = array[0];
        if (random32 < maxUnbiased) {
            return min + (random32 % range);
        }
        // retry if biased sample
    }
}

/**
 * Securely generate a random float between min (inclusive) and max (exclusive).
 * High precision, uniform distribution, Sonar S2245 compliant.
 *
 * @param {number} min - Minimum value
 * @param {number} max - Maximum value
 * @param {number} [decimals=2] - Number of decimal places
 * @returns {number} A cryptographically secure random float
 */
export function secureRandomFloat(min, max, decimals = 2) {
    const array = new Uint32Array(2);
    crypto.getRandomValues(array);

    // Combine two 32-bit integers to improve precision
    const high = array[0] / 0x100000000;
    const low = array[1] / 0x100000000;

    const normalized = (high + low) % 1; // uniform [0,1)
    const value = min + normalized * (max - min);

    return Number(value.toFixed(decimals));
}

/**
 * Generate a UUID (Version 4) using crypto.randomUUID if available, else fallback.
 *
 * @returns {string} RFC 4122-compliant UUID v4
 */
export function generateUuid() {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    // Manual fallback using secure random bytes
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);

    // Version and variant bits
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = [...bytes].map(b => b.toString(16).padStart(2, '0'));
    return [
        hex.slice(0, 4).join(''),
        hex.slice(4, 6).join(''),
        hex.slice(6, 8).join(''),
        hex.slice(8, 10).join(''),
        hex.slice(10, 16).join(''),
    ].join('-');
}

/**
 * Generate a random user object.
 *
 * @param {number} index - Index for uniqueness
 * @returns {{ name: string, email: string }}
 */
export function generateUser(index) {
    const id = generateUuid();
    return { name: `User ${index}`, email: `user-${index}-${id}@example.com` };
}

/**
 * Generate a random item object.
 *
 * @param {number} index - Index for uniqueness
 * @returns {{ sku: string, title: string, price: number }}
 */
export function generateItem(index) {
    const id = generateUuid();
    return {
        sku: `SKU-${id}`,
        title: `Item ${index}`,
        price: secureRandomFloat(1, 100, 2)
    };
}

/**
 * Slice first N% of array.
 *
 * @param {Array} arr - Source array
 * @param {number} percent - Fraction (0-1)
 * @returns {Array} Subset of array
 */
export function slicePercent(arr, percent) {
    return arr.slice(0, Math.floor(arr.length * percent));
}

/**
 * Recursively encodes a JavaScript object to `application/x-www-form-urlencoded`.
 *
 * Supports nested objects and arrays:
 *   encodeFormData({ user: { name: 'John' } })
 *   → "user[name]=John"
 *
 *   encodeFormData({ tags: ['a', 'b'] })
 *   → "tags[]=a&tags[]=b"
 *
 * @param {Record<string, any>} data - Input object
 * @param {string} [parentKey] - Internal recursion prefix
 * @returns {string} Encoded query string
 * @throws {TypeError} When data is not a plain object
 */
export function encodeFormData(data, parentKey = '') {
  if (!isEncodableObject(data)) {
    throw new TypeError('encodeFormData: Input must be a plain object or array.');
  }

  const pairs = [];

  for (const [key, value] of Object.entries(data)) {
    if (value == null) continue; // skip null/undefined

    const fullKey = getFullKey(parentKey, key, data);

    if (isEncodableObject(value)) {
      // recurse for nested objects or arrays
      pairs.push(encodeFormData(value, fullKey));
    } else {
      // primitive value
      pairs.push(encodePair(fullKey, value));
    }
  }

  return pairs.join('&');
}

/**
 * Builds a properly encoded key=value pair.
 * @param {string} key
 * @param {any} value
 * @returns {string}
 */
function encodePair(key, value) {
  return `${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
}

/**
 * Returns the nested key path according to Laravel-style conventions.
 * @param {string} parent
 * @param {string} key
 * @param {any} context
 * @returns {string}
 */
function getFullKey(parent, key, context) {
  if (!parent) return key;
  return Array.isArray(context) ? `${parent}[]` : `${parent}[${key}]`;
}

/**
 * Determines if the value is a plain object or array that can be recursively encoded.
 * @param {any} value
 * @returns {boolean}
 */
function isEncodableObject(value) {
  return typeof value === 'object' && value !== null;
}

/**
 * Helper: Records duration metrics and performs success check.
 * @param {import('k6/http').Response} res
 * @param {string} entity
 * @param {string} op
 * @param {import('k6/metrics').Trend} trend
 * @param {(number|number[])[]} expected
 */
export function recordTrendAndCheck(res, entity, op, trend, expected) {
    if (trend)  {
        trend.add(res.timings.duration);
    }
    const expectedStatuses = Array.isArray(expected) ? expected.flat() : [expected];
    console.log("expectedStatuses", expectedStatuses);
    console.log("res.status", res.status);
    if(expectedStatuses.includes(res.status)) {
        console.log("Passed expectedStatuses", expectedStatuses);
        console.log("Passed r.status", res.status);
    } else {
        console.log("Failed expectedStatuses", expectedStatuses);
        console.log("Failed r.status", res.status);
    }
    check(res, {
        [`${toUpperSnake(entity)} ${op.toUpperCase()} success`]: r =>
            expectedStatuses.includes(r.status),
    });
}

/**
 * Perform a POST to create an entity.
 *
 * @param {string} url - Endpoint URL
 * @param {object} obj - Payload
 * @param {import('k6/metrics').Trend} trend - Trend to record latency
 * @returns {string|null} Created entity ID
 */
export function postEntity(entity, url, obj, trend) {
    const res = http.post(url, JSON.stringify(obj), {
        headers: { 'Content-Type': 'application/json' },
    });
    recordTrendAndCheck(res, entity, "create", trend, [200, 201, 202]);

    if (res.status === 201) {
        try {
            const parsed = JSON.parse(res.body);
            return parsed?.id || null;
        } catch {
            // ignore malformed response
        }
    }
}

/**
 * Perform a GET to list entities.
 *
 * @param {string} url - Endpoint URL
 * @param {import('k6/metrics').Trend} trend - Trend to record latency
 * @returns {string|null} Created entity ID
 */
export function getEntities(entity, url, trend) {
    const res = http.get(url);
    recordTrendAndCheck(res, entity, "list", trend, 200);

    try {
        return JSON.parse(res.body) || [];
    } catch {
        return null;
    }
}

/**
 * Perform a GET to read entity.
 *
 * @param {string} url - Endpoint URL
 * @param {import('k6/metrics').Trend} trend - Trend to record latency
 * @returns {string|null} Created entity ID
 */
export function getEntity(entity, url, trend) {
    const res = http.get(`${url}`);
    recordTrendAndCheck(res, entity, "read", trend, 200);

    try {
        const parsed = JSON.parse(res.body);
        return parsed?.id || null;
    } catch {
        return null;
    }
}

/**
 * Converts a given string to UPPER_SNAKE_CASE.
 *
 * Examples:
 *  - "users"        -> "USERS"
 *  - "async-users"  -> "ASYNC_USERS"
 *  - "asyncItems"   -> "ASYNC_ITEMS"
 *
 * @param {string} str
 * @returns {string}
 */
export function toUpperSnake(str) {
  return str
    .replace(/([a-z])([A-Z])/g, '$1_$2') // handle camelCase → snake
    .replace(/[-\s]+/g, '_')             // handle kebab-case / spaces → underscore
    .toUpperCase();
}
