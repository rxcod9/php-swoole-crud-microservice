/**
 * @file lib/utils.js
 * @description Contains shared helper functions for entity generation and randomization.
 */

import http from 'k6/http';
import { check } from 'k6';

/**
 * Secure random integer generator using crypto.getRandomValues.
 * Fully compliant with Sonar rule S2245 (no Math.random()).
 *
 * @param {number} min - Minimum value (inclusive)
 * @param {number} max - Maximum value (exclusive)
 * @returns {number} Secure random integer between min and max - 1
 */
export function secureRandomInt(min, max) {
  if (max < min) {
    throw new Error(`Invalid range: max (${max}) must be greater than min (${min})`);
  }

  // Generate a 32-bit unsigned random integer
  const array = new Uint32Array(1);
  crypto.getRandomValues(array);

  // Normalize to [0, 1)
  const normalized = array[0] / 0xffffffff;

  // Scale to the requested range
  const value = Math.floor(normalized * (max - min) + min);

  return value;
}

/**
 * Secure random float generator using crypto.getRandomValues.
 * Replaces Math.random() for Sonar S2245 compliance.
 *
 * @param {number} min - Minimum value (inclusive)
 * @param {number} max - Maximum value (exclusive)
 * @param {number} [decimals=2] - Number of decimal places
 * @returns {number} Secure random float between min and max
 */
export function secureRandomFloat(min, max, decimals = 2) {
  const array = new Uint32Array(1);
  crypto.getRandomValues(array);

  // Scale down to [0, 1)
  const normalized = array[0] / 0xffffffff;

  // Scale to [min, max)
  const value = min + normalized * (max - min);

  // Round to given decimals
  return Number(value.toFixed(decimals));
}


/**
 * Generate a UUID (Version 4).
 *
 * @returns {string} Randomly generated UUID v4
 */
export function generateUuid() {
    const template = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
    return template.replace(/[xy]/g, c => {
        // Non-secure random for performance (acceptable for load testing)
        const r = secureRandomInt(0, 16); // replaces Math.random()
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
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
 * Perform a POST to create an entity.
 *
 * @param {string} url - Endpoint URL
 * @param {object} obj - Payload
 * @param {import('k6/metrics').Trend} trend - Trend to record latency
 * @returns {string|null} Created entity ID
 */
export function postEntity(url, obj, trend) {
    const res = http.post(url, JSON.stringify(obj), {
        headers: { 'Content-Type': 'application/json' }
    });

    trend.add(res.timings.duration);
    check(res, { 'POST success': r => r.status === 201 });

    try {
        const parsed = JSON.parse(res.body);
        return parsed?.id || null;
    } catch {
        return null;
    }
}
