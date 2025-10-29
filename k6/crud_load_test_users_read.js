import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

/**
 * --------------------
 * ENVIRONMENT VARIABLES
 * --------------------
 * All environment variables can be overridden via CLI using -e VAR=value
 */
const ENV = {
    BASE_URL: __ENV.BASE_URL || 'http://localhost:9501',           // Base API URL
    TOTAL_USERS: Number(__ENV.TOTAL_USERS) || 200,                 // Total users to create in setup
    HOT_PERCENT: Number(__ENV.HOT_PERCENT) || 0.1,                 // Top N% of entities marked 'hot'
    COOL_PERCENT: Number(__ENV.COOL_PERCENT) || 0.1,               // Top N% of entities marked 'cool'
    HOT_READ_RATIO: Number(__ENV.HOT_READ_RATIO) || 0.8,           // Probability of reading hot IDs
    HOT_UPDATE_RATIO: Number(__ENV.HOT_UPDATE_RATIO) || 0.01,      // Probability of updating hot IDs
    LIST_PAGES: Number(__ENV.LIST_PAGES) || 3,                     // Number of pages for list endpoints
    TOTAL_EXECUTIONS: Number(__ENV.TOTAL_EXECUTIONS) || 2000,      // Max iterations per VU
    MAX_DURATION: __ENV.MAX_DURATION || '10m',                      // Setup/teardown timeout
    MAX_VUS: Number(__ENV.MAX_VUS) || 200                          // Maximum virtual users
};

/**
 * --------------------
 * METRICS
 * --------------------
 * Custom trends to track latency for CRUD operations per entity
 */
const listTrendUsers = new Trend('USERS_LIST_latency_ms');
const readTrendUsers = new Trend('USERS_READ_latency_ms');

/**
 * --------------------
 * PER-VU STATE
 * --------------------
 * Track IDs per VU and execution count
 */
let VU_userIds = [];
let globalExecutions = 0;

/**
 * --------------------
 * UTIL FUNCTIONS
 * --------------------
 */

/**
 * Generates a UUID (version 4)
 * Example: 123e4567-e89b-12d3-a456-426614174000
 *
 * @returns {string} UUID v4
 */
function generateUuid() {
    let template = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';

    // Replace 'x' placeholders
    template = template.replaceAll('x', () => {
        // sonarjs:S2245 -- not security-sensitive
        const r = Math.floor(Math.random() * 16);
        return r.toString(16);
    });

    // Replace 'y' placeholders
    template = template.replaceAll('y', () => {
        // sonarjs:S2245 -- not security-sensitive
        const r = Math.floor(Math.random() * 16);
        const v = (r & 0x3) | 0x8; // UUID variant bits
        return v.toString(16);
    });

    return template;
}

/**
 * Generate random user object
 * @param {number} index - Index for uniqueness
 * @returns {{name: string, email: string}}
 */
function generateUser(index) {
    const id = generateUuid();
    return { name: `User ${index} ${id}`, email: `user-${index}-${id}@example.com` };
}

/**
 * Get random element from array
 * @param {Array} arr 
 * @returns any
 */
function randomItem(arr) {
    if (!arr || arr.length === 0) return null;
    // sonarjs:S2245 -- not security-sensitive
    return arr[Math.floor(Math.random() * arr.length)];
}

/**
 * POST entity and return its ID
 * @param {string} url - API endpoint
 * @param {object} obj - Object to POST
 * @param {Trend} trend - Trend to track latency
 * @returns {string|null} id
 */
function postEntity(url, obj, trend) {
    const res = http.post(url, JSON.stringify(obj), { headers: { 'Content-Type': 'application/json' } });
    check(res, { 'CREATE success': r => r.status === 201 });
    if (res.status === 201) {
        try {
            const parsed = JSON.parse(res.body);
            return parsed?.id || null;
        } catch (e) {
            console.error(`[POST PARSE ERROR] URL: ${url} | Response: ${res.body}`, e);
        }
    }
    return null;
}

/**
 * Warm cache for paginated list endpoints
 * @param {string} baseUrl - API endpoint (users)
 * @param {number} pages - Number of pages to request
 * @param {Trend} trend - optional latency tracking
 */
function warmListCache(baseUrl, pages) {
    console.log(`[CACHE WARMUP] Warming list cache for ${baseUrl}, ${pages} pages`);
    for (let page = 1; page <= pages; page++) {
        const url = `${ENV.BASE_URL}/${baseUrl}?page=${page}`;
        const res = http.get(url);
        check(res, { [`${baseUrl.toUpperCase()} LIST warm success`]: r => r.status === 200 }) ||
            console.error(`[CACHE WARMUP LIST FAILED] URL: ${url} | Status: ${res.status}`);
    }
}

/**
 * Warm cache by reading hot IDs
 * @param {string[]} hotIds - Array of hot IDs
 * @param {string} baseUrl - API endpoint (users)
 */
function warmReadCache(hotIds, baseUrl) {
    console.log(`[CACHE WARMUP] Warming ${hotIds.length} hot IDs for ${baseUrl}`);
    hotIds.forEach(id => {
        const url = `${ENV.BASE_URL}/${baseUrl}/${id}`;
        const res = http.get(url);
        check(res, { [`${baseUrl.toUpperCase()} warm READ success`]: r => r.status === 200 }) ||
            console.error(`[CACHE WARMUP FAILED] URL: ${url} | Status: ${res.status}`);
    });
}

/**
 * Slice first N% of array
 * @param {Array} arr 
 * @param {number} percent 
 * @returns {Array}
 */
function slicePercent(arr, percent) {
    return arr.slice(0, Math.floor(arr.length * percent));
}

/**
 * Print usage instructions for all environment variables
 */
function printUsage() {
    console.log(`
k6 script environment variables:

BASE_URL            - Base API URL (default: http://localhost:9501)
TOTAL_USERS         - Number of users to create (default: 200)
HOT_PERCENT         - Percentage of hot entities (default: 0.1)
COOL_PERCENT        - Percentage of cool entities (default: 0.1)
HOT_READ_RATIO      - Chance of reading hot IDs (default: 0.8)
HOT_UPDATE_RATIO    - Chance of updating hot IDs (default: 0.01)
LIST_PAGES          - Number of pages for list endpoints (default: 3)
TOTAL_EXECUTIONS    - Max iterations per VU (default: 1000)
MAX_DURATION        - Setup/teardown timeout (default: 5m)
MAX_VUS             - Maximum virtual users (default: 100)
`);
}

/**
 * --------------------
 * SETUP
 * --------------------
 * Create initial users, determine hot/cool IDs
 * @returns {Object} setup data
 */
export function setup() {
    console.log('=== ENVIRONMENT VARIABLES ===');
    Object.entries(ENV).forEach(([key, value]) => console.log(`${key}: ${value}`));
    console.log('==============================');
    printUsage();

    // Helper to create multiple entities and return IDs
    const createEntities = (total, generateFn, baseUrl, trend) => {
        const ids = [];
        for (let i = 0; i < total; i++) {
            const id = postEntity(`${ENV.BASE_URL}/${baseUrl}`, generateFn(i), trend);
            if (id) ids.push(id);
        }
        return ids;
    };

    // Create users
    const userIds = createEntities(
        ENV.TOTAL_USERS,
        generateUser,
        'users',
        // createTrendUsers
    );

    // Determine hot and cool IDs
    const hotUserIds = slicePercent(userIds, ENV.HOT_PERCENT);
    const coolUserIds = slicePercent(userIds.filter(id => !hotUserIds.includes(id)), ENV.COOL_PERCENT);

    // --- WARM CACHE ---
    warmReadCache(hotUserIds, 'users');

    // --- WARM CACHE for paginated lists ---
    warmListCache('users', ENV.LIST_PAGES);

    return {
        userIds,
        hotUserIds,
        coolUserIds
    };
}

/**
 * --------------------
 * PERFORM CRUD ACTION
 * --------------------
 * Weighted random selection of CRUD operations per entity
 * @param {Object} options 
 */
function performCrudAction({
    vuIds,
    hotIds,
    weights,
    baseUrl,
    trends,
    entity
}) {
    // sonarjs:S2245 -- not security-sensitive
    const r = Math.random();

    const actions = [
        {
            type: 'list',
            weight: weights.LIST,
            handler: () => {
                // sonarjs:S2245 -- not security-sensitive
                const page = Math.floor(Math.random() * ENV.LIST_PAGES) + 1;
                const url = `${ENV.BASE_URL}/${baseUrl}?page=${page}`;
                const res = http.get(url);
                console.log(`[${entity} LIST SUCCESS] URL: ${url} | Status: ${res.status} | CacheType: ${res.headers['X-Cache-Type']}`);
                trends.list.add(res.timings.duration);
                check(res, { [`${entity} LIST success`]: r => r.status === 200 }) ||
                    console.error(`[${entity} LIST FAILED] URL: ${url} | Status: ${res.status}`);
            }
        },
        {
            type: 'read',
            weight: weights.READ,
            handler: () => {
                // sonarjs:S2245 -- not security-sensitive
                // const id = hotIds.length && Math.random() < ENV.HOT_READ_RATIO ? randomItem(hotIds) : randomItem(vuIds);
                const id = randomItem(hotIds);
                if (!id) return console.warn(`[${entity}] Skipping read: no ID available`);
                const url = `${ENV.BASE_URL}/${baseUrl}/${id}`;
                const res = http.get(url);
                console.log(`[${entity} READ SUCCESS] URL: ${url} | ID: ${id} | Status: ${res.status} | CacheType: ${res.headers['X-Cache-Type']}`);
                trends.read.add(res.timings.duration);
                check(res, { [`${entity} READ success`]: r => r.status === 200 }) ||
                    console.error(`[${entity} READ FAILED] URL: ${url} | ID: ${id} | Status: ${res.status}`);
            }
        }
    ].filter(a => a.weight);

    // Weighted selection
    const totalWeight = actions.reduce((sum, a) => sum + a.weight, 0);
    let cumulative = 0;
    for (const action of actions) {
        cumulative += action.weight / totalWeight;
        if (r < cumulative) {
            action.handler();
            break;
        }
    }
}

/**
 * --------------------
 * OPTIONS
 * --------------------
 */
export const options = {
    setupTimeout: ENV.MAX_DURATION,
    teardownTimeout: ENV.MAX_DURATION,
    stages: [
        { duration: '8s', target: 0.1 },
        { duration: '8s', target: 0.25 },
        { duration: '8s', target: 0.4 },
        { duration: '16s', target: 0.6 },
        { duration: '16s', target: 0.8 },
        { duration: '16s', target: 1 },
        { duration: '16s', target: 0.8 },
        { duration: '8s', target: 0.5 },
        { duration: '8s', target: 0.25 },
        { duration: '8s', target: 0 },
    ].map(s => ({ ...s, target: Math.floor(s.target * ENV.MAX_VUS) })),
    thresholds: {
        'http_req_duration': ['p(95)<200'],
        'USERS_LIST_latency_ms': ['avg<100'],
        'USERS_READ_latency_ms': ['avg<50'],
    }
};

/**
 * --------------------
 * DEFAULT FUNCTION
 * --------------------
 */
export default function (data) {
    if (globalExecutions >= ENV.TOTAL_EXECUTIONS) return;
    globalExecutions++;

    if (VU_userIds.length === 0) VU_userIds = data.userIds.slice();

    // Perform USERS CRUD
    performCrudAction({
        vuIds: VU_userIds,
        hotIds: data.hotUserIds,
        weights: { LIST: 0.5, READ: 0.5 },
        generateFn: generateUser,
        baseUrl: 'users',
        trends: {
            list: listTrendUsers,
            read: readTrendUsers,
            // create: createTrendUsers,
            // update: updateTrendUsers
        },
        entity: 'USERS'
    });

    // sonarjs:S2245 -- not security-sensitive
    sleep(Math.random() * 2);
}

/**
 * --------------------
 * TEARDOWN
 * --------------------
 * Cleanup all created users
 * @param {Object} data 
 */
export function teardown(data) {
    console.log(`Cleaning up users`);
    for (let id of data.userIds) http.del(`${ENV.BASE_URL}/users/${id}`);
}
