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
    TOTAL_ITEMS: Number(__ENV.TOTAL_ITEMS) || 200,                 // Total items to create in setup
    HOT_PERCENT: Number(__ENV.HOT_PERCENT) || 0.1,                 // Top N% of entities marked 'hot'
    COOL_PERCENT: Number(__ENV.COOL_PERCENT) || 0.1,               // Top N% of entities marked 'cool'
    HOT_READ_RATIO: Number(__ENV.HOT_READ_RATIO) || 0.8,           // Probability of reading hot IDs
    HOT_UPDATE_RATIO: Number(__ENV.HOT_UPDATE_RATIO) || 0.01,      // Probability of updating hot IDs
    LIST_PAGES: Number(__ENV.LIST_PAGES) || 3,                     // Number of pages for list endpoints
    TOTAL_EXECUTIONS: Number(__ENV.TOTAL_EXECUTIONS) || 2000,      // Max iterations per VU
    MAX_DURATION: __ENV.MAX_DURATION || '10m',                      // Setup/teardown timeout
    MAX_VUS: Number(__ENV.MAX_VUS) || 50                          // Maximum virtual users
};

/**
 * --------------------
 * METRICS
 * --------------------
 * Custom trends to track latency for CRUD operations per entity
 */
const listTrendUsers = new Trend('USERS_LIST_latency_ms');
const readTrendUsers = new Trend('USERS_READ_latency_ms');
const createTrendUsers = new Trend('USERS_CREATE_latency_ms');
const updateTrendUsers = new Trend('USERS_UPDATE_latency_ms');

const listTrendItems = new Trend('ITEMS_LIST_latency_ms');
const readTrendItems = new Trend('ITEMS_READ_latency_ms');
const createTrendItems = new Trend('ITEMS_CREATE_latency_ms');
const updateTrendItems = new Trend('ITEMS_UPDATE_latency_ms');

/**
 * --------------------
 * PER-VU STATE
 * --------------------
 * Track IDs per VU and execution count
 */
let VU_userIds = [];
let VU_itemIds = [];
let globalExecutions = 0;

/**
 * --------------------
 * UTIL FUNCTIONS
 * --------------------
 */

/**
 * Generate UUIDv4-like string
 * @returns {string} uuid
 */
function generateUuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = Math.floor(Math.random() * 16); // ensure integer
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
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
 * Generate random item object
 * @param {number} index - Index for uniqueness
 * @returns {{sku: string, title: string, price: number}}
 */
function generateItem(index) {
    const id = generateUuid();
    return {
        sku: `sku-item-${index}-${id}`,
        title: `Item ${index} ${id}`,
        price: Math.floor(Math.random() * 100)
    };
}

/**
 * Get random element from array
 * @param {Array} arr 
 * @returns any
 */
function randomItem(arr) {
    if (!arr || arr.length === 0) return null;
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
    trend.add(res.timings.duration);
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
TOTAL_ITEMS         - Number of items to create (default: 200)
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
 * Create initial users and items, determine hot/cool IDs
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

    // Create users and items
    const userIds = createEntities(ENV.TOTAL_USERS, generateUser, 'users', createTrendUsers);
    const itemIds = createEntities(ENV.TOTAL_ITEMS, generateItem, 'items', createTrendItems);

    // Determine hot and cool IDs
    const hotUserIds = slicePercent(userIds, ENV.HOT_PERCENT);
    const hotItemIds = slicePercent(itemIds, ENV.HOT_PERCENT);
    const coolUserIds = slicePercent(userIds.filter(id => !hotUserIds.includes(id)), ENV.COOL_PERCENT);
    const coolItemIds = slicePercent(itemIds.filter(id => !hotItemIds.includes(id)), ENV.COOL_PERCENT);

    return { userIds, itemIds, hotUserIds, hotItemIds, coolUserIds, coolItemIds };
}

/**
 * --------------------
 * PERFORM CRUD ACTION
 * --------------------
 * Weighted random selection of CRUD operations per entity
 * @param {Object} options 
 */
function performCrudAction({ vuIds, hotIds, coolIds, weights, generateFn, baseUrl, trends, entity }) {
    const r = Math.random();

    const actions = [
        {
            type: 'list',
            weight: weights.LIST,
            handler: () => {
                const page = Math.floor(Math.random() * ENV.LIST_PAGES) + 1;
                const url = `${ENV.BASE_URL}/${baseUrl}?page=${page}`;
                const res = http.get(url);
                trends.list.add(res.timings.duration);
                check(res, { [`${entity} LIST success`]: r => r.status === 200 }) ||
                    console.error(`[${entity} LIST FAILED] URL: ${url} | Status: ${res.status}`);
            }
        },
        {
            type: 'read',
            weight: weights.READ,
            handler: () => {
                const id = hotIds.length && Math.random() < ENV.HOT_READ_RATIO ? randomItem(hotIds) : randomItem(vuIds);
                if (!id) return console.warn(`[${entity}] Skipping read: no ID available`);
                const url = `${ENV.BASE_URL}/${baseUrl}/${id}`;
                const res = http.get(url);
                trends.read.add(res.timings.duration);
                check(res, { [`${entity} READ success`]: r => r.status === 200 }) ||
                    console.error(`[${entity} READ FAILED] URL: ${url} | ID: ${id} | Status: ${res.status}`);
            }
        },
        {
            type: 'create',
            weight: weights.CREATE,
            handler: () => {
                const obj = generateFn(Math.floor(Math.random() * 1_000_000));
                const res = http.post(`${ENV.BASE_URL}/${baseUrl}`, JSON.stringify(obj), { headers: { 'Content-Type': 'application/json' } });
                trends.create?.add(res.timings.duration);
                check(res, { [`${entity} CREATE success`]: r => r.status === 201 }) ||
                    console.error(`[${entity} CREATE FAILED] URL: ${baseUrl} | Payload: ${JSON.stringify(obj)} | Status: ${res.status}`);
                if (res.status === 201) {
                    try {
                        const parsed = JSON.parse(res.body);
                        if (parsed?.id != null) vuIds.push(parsed.id);
                        else console.warn(`[${entity} CREATE WARNING] Response missing 'id': ${res.body}`);
                    } catch (e) {
                        console.error(`[${entity} CREATE PARSE ERROR] Response: ${res.body}`, e);
                    }
                }
            }
        },
        {
            type: 'update',
            weight: weights.UPDATE,
            handler: () => {
                const id = hotIds.length && Math.random() < ENV.HOT_UPDATE_RATIO ? randomItem(hotIds) : randomItem(vuIds);
                if (!id) return console.warn(`[${entity}] Skipping update: no ID available`);
                if (coolIds.includes(id)) return console.warn(`[${entity}] Skipping update: cool ID`);
                const obj = generateFn(id);
                const url = `${ENV.BASE_URL}/${baseUrl}/${id}`;
                const res = http.put(url, JSON.stringify({ ...obj, updated: true }), { headers: { 'Content-Type': 'application/json' } });
                trends.update?.add(res.timings.duration);
                check(res, { [`${entity} UPDATE success`]: r => r.status === 200 }) ||
                    console.error(`[${entity} UPDATE FAILED] URL: ${url} | Payload: ${JSON.stringify(obj)} | Status: ${res.status}`);
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
        { duration: '2s', target: 0.1 },
        { duration: '2s', target: 0.25 },
        { duration: '2s', target: 0.4 },
        { duration: '4s', target: 0.6 },
        { duration: '4s', target: 0.8 },
        { duration: '4s', target: 1 },
        { duration: '4s', target: 0.8 },
        { duration: '4s', target: 0.5 },
        { duration: '2s', target: 0.25 },
        { duration: '2s', target: 0 },
    ].map(s => ({ ...s, target: Math.floor(s.target * ENV.MAX_VUS) })),
    thresholds: {
        'http_req_duration': ['p(95)<200'],
        'USERS_LIST_latency_ms': ['avg<100'],
        'USERS_CREATE_latency_ms': ['avg<100'],
        'USERS_READ_latency_ms': ['avg<50'],
        'USERS_UPDATE_latency_ms': ['avg<100'],
        'ITEMS_LIST_latency_ms': ['avg<100'],
        'ITEMS_CREATE_latency_ms': ['avg<100'],
        'ITEMS_READ_latency_ms': ['avg<50'],
        'ITEMS_UPDATE_latency_ms': ['avg<100'],
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
    if (VU_itemIds.length === 0) VU_itemIds = data.itemIds.slice();

    // Perform USERS CRUD
    performCrudAction({
        vuIds: VU_userIds,
        hotIds: data.hotUserIds,
        coolIds: data.coolUserIds,
        weights: { LIST: 0.5, READ: 0.25, CREATE: 0.15, UPDATE: 0.07 },
        generateFn: generateUser,
        baseUrl: 'users',
        trends: { list: listTrendUsers, read: readTrendUsers, create: createTrendUsers, update: updateTrendUsers },
        entity: 'USERS'
    });

    // Perform ITEMS CRUD
    performCrudAction({
        vuIds: VU_itemIds,
        hotIds: data.hotItemIds,
        coolIds: data.coolItemIds,
        weights: { LIST: 0.5, READ: 0.25, CREATE: 0.15, UPDATE: 0.07 },
        generateFn: generateItem,
        baseUrl: 'items',
        trends: { list: listTrendItems, read: readTrendItems, create: createTrendItems, update: updateTrendItems },
        entity: 'ITEMS'
    });

    sleep(Math.random() * 2);
}

/**
 * --------------------
 * TEARDOWN
 * --------------------
 * Cleanup all created users and items
 * @param {Object} data 
 */
export function teardown(data) {
    console.log(`Cleaning up users and items`);
    for (let id of data.userIds) http.del(`${ENV.BASE_URL}/users/${id}`);
    for (let id of data.itemIds) http.del(`${ENV.BASE_URL}/items/${id}`);
}
