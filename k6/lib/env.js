/**
 * @file lib/env.js
 * @description Centralized environment configuration for K6 test scripts.
 * Supports dynamic entities, CRUD operations, and safe parsing of environment variables.
 * All variables can be overridden via the K6 CLI using `-e VAR=value`.
 */

/**
 * Safely parse comma-separated environment variable into an array.
 *
 * @param {string|undefined} val - Raw environment value (e.g., "users,items,orders")
 * @param {string[]} defaults - Default fallback if env not defined
 * @returns {string[]} Parsed and trimmed array
 */
function parseList(val, defaults) {
    if (!val || typeof val !== 'string') return defaults;
    return val
        .split(',')
        .map(v => v.trim())
        .filter(Boolean);
}

/**
 * Global environment configuration object.
 *
 * @constant
 * @type {{
 *   BASE_URL: string,
 *   ENTITIES: string[],
 *   CRUD: string[],
 *   TOTAL_ENTITIES: number,
 *   HOT_PERCENT: number,
 *   COOL_PERCENT: number,
 *   TOTAL_EXECUTIONS: number,
 *   MAX_VUS: number,
 *   MAX_DURATION: string
 * }}
 */
export const ENV = {
    BASE_URL: __ENV.BASE_URL || 'http://localhost:9501',

    // Allow comma-separated entities (e.g., users,items,orders)
    ENTITIES: parseList(__ENV.ENTITIES, ['users', 'items', 'async-users']),

    // Allow comma-separated CRUD operations (e.g., list,read,create,update,delete)
    CRUD: parseList(__ENV.CRUD, ['list', 'read', 'create', 'update']),

    TOTAL_ENTITIES: Number(__ENV.TOTAL_ENTITIES) || 2000,
    HOT_PERCENT: Number(__ENV.HOT_PERCENT) || 0.1,
    COOL_PERCENT: Number(__ENV.COOL_PERCENT) || 0.1,
    TOTAL_EXECUTIONS: Number(__ENV.TOTAL_EXECUTIONS) || 20000,
    MAX_VUS: Number(__ENV.MAX_VUS) || 200,
    MAX_DURATION: __ENV.MAX_DURATION || '10m'
};

/**
 * Prints the current environment configuration in a readable format.
 *
 * @returns {void}
 */
export function printUsage() {
    console.log('================= ENVIRONMENT CONFIG =================');
    for (const [key, val] of Object.entries(ENV)) {
        const formatted =
            Array.isArray(val) ? val.join(', ') : typeof val === 'object' ? JSON.stringify(val) : val;
        console.log(`${key}: ${formatted}`);
    }
    console.log('======================================================');
}
