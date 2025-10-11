import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

// --------------------
// CONFIGURATION VARIABLES
// --------------------
const CONFIG = {
    TOTAL_USERS: 2000,
    TOTAL_ITEMS: 2000,
    HOT_PERCENT: 0.1,          // Top 10% are hot (never deleted)
    COOL_PERCENT: 0.1,         // Top 10% are not hot (to be deleted)
    HOT_READ_RATIO: 0.8,       // 80% of reads go to hot IDs
    HOT_UPDATE_RATIO: 0.01,    // 1% of updates go to hot IDs
    LIST_PAGES: 3,
    WEIGHTS_USERS: {
        LIST: 0.5,
        READ: 0.25,
        CREATE: 0.15,
        UPDATE: 0.07,
    },
    WEIGHTS_ITEMS: {
        LIST: 0.5,
        READ: 0.25,
        CREATE: 0.15,
        UPDATE: 0.07,
    },
    CONCURRENCY: {
        MAX_VUS: 500,
        STAGES: [
            { duration: '1m', target: 0.10 },
            { duration: '1m', target: 0.25 },
            { duration: '1m', target: 0.40 },
            { duration: '2m', target: 0.60 },
            { duration: '2m', target: 0.80 },
            { duration: '2m', target: 1.00 },
            { duration: '2m', target: 0.80 },
            { duration: '2m', target: 0.50 },
            { duration: '1m', target: 0.25 },
            { duration: '1m', target: 0.0 },
        ]
    },
    TOTAL_EXECUTIONS: 10000,
    MAX_DURATION: '5m'
};

// --------------------
// METRICS
// --------------------
let listTrendUsers = new Trend('USERS_LIST_latency_ms');
let readTrendUsers = new Trend('USERS_READ_latency_ms');
let createTrendUsers = new Trend('USERS_CREATE_latency_ms');
let updateTrendUsers = new Trend('USERS_UPDATE_latency_ms');

let listTrendItems = new Trend('ITEMS_LIST_latency_ms');
let readTrendItems = new Trend('ITEMS_READ_latency_ms');
let createTrendItems = new Trend('ITEMS_CREATE_latency_ms');
let updateTrendItems = new Trend('ITEMS_UPDATE_latency_ms');

// --------------------
// OPTIONS
// --------------------
export const options = {
    setupTimeout: CONFIG.MAX_DURATION,
    teardownTimeout: CONFIG.MAX_DURATION,
    stages: CONFIG.CONCURRENCY.STAGES.map(s => ({
        duration: s.duration,
        target: Math.floor(s.target * CONFIG.CONCURRENCY.MAX_VUS)
    })),
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

// --------------------
// HELPERS
// --------------------
function generateUser(index) {
    let id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
    return {
        name: `user-${id}-${index}`,
        email: `user-${id}-${index}@example.com`
    };
}

function generateItem(index) {
    let id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
    return {
        sku: `sku-item-${index}-${id}`,
        title: `Item ${index} ${id}`,
        price: Math.floor(Math.random() * 100)
    };
}

function randomItem(arr) { 
    if (!arr || arr.length === 0) return null; 
    return arr[Math.floor(Math.random() * arr.length)]; 
}

// --------------------
// PER-VU ID TRACKERS
// --------------------
let VU_userIds = [];
let VU_itemIds = [];

// --------------------
// GLOBAL EXECUTION TRACKER
// --------------------
let globalExecutions = 0;

// --------------------
// SETUP
// --------------------
export function setup() {
    let userIds = [];
    for (let i = 0; i < CONFIG.TOTAL_USERS; i++) {
        const user = generateUser(i);
        const res = http.post('http://localhost:9501/users', JSON.stringify(user), {
            headers: { 'Content-Type': 'application/json' }
        });
        createTrendUsers.add(res.timings.duration);
        check(res, { 'CREATE success': r => r.status === 201 });
        try {
            if (res.status === 201) {
                const parsed = JSON.parse(res.body);
                if (parsed?.id != null) userIds.push(parsed.id);
            }
        } catch (e) {
            console.error('[SETUP] Failed parse CREATE response', res.body, e);
        }
    }

    let itemIds = [];
    for (let i = 0; i < CONFIG.TOTAL_ITEMS; i++) {
        const item = generateItem(i);
        const res = http.post('http://localhost:9501/items', JSON.stringify(item), {
            headers: { 'Content-Type': 'application/json' }
        });
        createTrendItems.add(res.timings.duration);
        check(res, { 'CREATE success': r => r.status === 201 });
        try {
            if (res.status === 201) {
                const parsed = JSON.parse(res.body);
                if (parsed?.id != null) itemIds.push(parsed.id);
            }
        } catch (e) {
            console.error('[SETUP] Failed parse CREATE response', res.body, e);
        }
    }

    // HOT & COOL IDs
    const hotUserIds = userIds.slice(0, Math.floor(CONFIG.TOTAL_USERS * CONFIG.HOT_PERCENT));
    const hotItemIds = itemIds.slice(0, Math.floor(CONFIG.TOTAL_ITEMS * CONFIG.HOT_PERCENT));
    const coolUserIds = userIds.filter(id => !hotUserIds.includes(id)).slice(0, Math.floor(CONFIG.TOTAL_USERS * CONFIG.COOL_PERCENT));
    const coolItemIds = itemIds.filter(id => !hotItemIds.includes(id)).slice(0, Math.floor(CONFIG.TOTAL_ITEMS * CONFIG.COOL_PERCENT));

    return {
        userIds,
        itemIds,
        hotUserIds,
        hotItemIds,
        coolUserIds,
        coolItemIds
    };
}

// --------------------
// DEFAULT FUNCTION
// --------------------
export default function (data) {
    if (globalExecutions >= CONFIG.TOTAL_EXECUTIONS) return;
    globalExecutions++;

    if (VU_userIds.length === 0) VU_userIds = data.userIds.slice();
    if (VU_itemIds.length === 0) VU_itemIds = data.itemIds.slice();

    function performCrudAction({ vuIds, hotIds, coolIds, weights, generateFn, baseUrl, trends, entity }) {
        const r = Math.random();

        const actions = [
            {
                type: 'list',
                weight: weights.LIST,
                handler: () => {
                    const page = Math.floor(Math.random() * CONFIG.LIST_PAGES) + 1;
                    const url = `${baseUrl}?page=${page}`;
                    const res = http.get(url);
                    trends.list.add(res.timings.duration);
                    const success = check(res, { [`${entity} LIST success`]: r => r.status === 200 });
                    if (!success) console.error(`[${entity} LIST FAILED] URL: ${url} | Status: ${res.status} | Response: ${res.body}`);
                },
            },
            {
                type: 'read',
                weight: weights.READ,
                handler: () => {
                    const id = hotIds.length && Math.random() < CONFIG.HOT_READ_RATIO ? randomItem(hotIds) : randomItem(vuIds);
                    if (!id) return console.warn(`[${entity}] Skipping read: no ID available`);
                    const url = `${baseUrl}/${id}`;
                    const res = http.get(url);
                    trends.read.add(res.timings.duration);
                    const success = check(res, { [`${entity} READ success`]: r => r.status === 200 });
                    if (!success) console.error(`[${entity} READ FAILED] URL: ${url} | ID: ${id} | Status: ${res.status} | Response: ${res.body}`);
                },
            },
            {
                type: 'create',
                weight: weights.CREATE,
                handler: () => {
                    const obj = generateFn(Math.floor(Math.random() * 1_000_000));
                    const res = http.post(baseUrl, JSON.stringify(obj), { headers: { 'Content-Type': 'application/json' } });
                    trends.create?.add(res.timings.duration);
                    const success = check(res, { [`${entity} CREATE success`]: r => r.status === 201 });
                    if (!success) console.error(`[${entity} CREATE FAILED] URL: ${baseUrl} | Payload: ${JSON.stringify(obj)} | Status: ${res.status} | Response: ${res.body}`);
                    try {
                        if (res.status === 201) {
                            const parsed = JSON.parse(res.body);
                            if (parsed?.id != null) vuIds.push(parsed.id);
                            else console.warn(`[${entity} CREATE WARNING] Response missing 'id': ${res.body}`);
                        }
                    } catch (e) {
                        console.error(`[${entity} CREATE PARSE ERROR] Response: ${res.body}`, e);
                    }
                },
            },
            {
                type: 'update',
                weight: weights.UPDATE,
                handler: () => {
                    const id = hotIds.length && Math.random() < CONFIG.HOT_UPDATE_RATIO ? randomItem(hotIds) : randomItem(vuIds);
                    if (!id) return console.warn(`[${entity}] Skipping update: no ID available`);
                    if (coolIds.includes(id)) return console.warn(`[${entity}] Skipping update: cool ID`);
                    const obj = generateFn(id);
                    const url = `${baseUrl}/${id}`;
                    const res = http.put(url, JSON.stringify({ ...obj, updated: true }), { headers: { 'Content-Type': 'application/json' } });
                    trends.update?.add(res.timings.duration);
                    const success = check(res, { [`${entity} UPDATE success`]: r => r.status === 200 });
                    if (!success) console.error(`[${entity} UPDATE FAILED] URL: ${url} | Payload: ${JSON.stringify(obj)} | Status: ${res.status} | Response: ${res.body}`);
                },
            },
        ].filter(a => a.weight);

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

    // USERS CRUD
    performCrudAction({
        vuIds: VU_userIds,
        hotIds: data.hotUserIds,
        coolIds: data.coolUserIds,
        weights: CONFIG.WEIGHTS_USERS,
        generateFn: generateUser,
        baseUrl: 'http://localhost:9501/users',
        trends: { list: listTrendUsers, read: readTrendUsers, create: createTrendUsers, update: updateTrendUsers },
        entity: 'USERS'
    });

    // ITEMS CRUD
    performCrudAction({
        vuIds: VU_itemIds,
        hotIds: data.hotItemIds,
        coolIds: data.coolItemIds,
        weights: CONFIG.WEIGHTS_ITEMS,
        generateFn: generateItem,
        baseUrl: 'http://localhost:9501/items',
        trends: { list: listTrendItems, read: readTrendItems, create: createTrendItems, update: updateTrendItems },
        entity: 'ITEMS'
    });

    sleep(Math.random() * 2);
}

// --------------------
// TEARDOWN
// --------------------
export function teardown(data) {
    console.log(`Cleaning up users and items`);
    for (let id of data.userIds) http.del(`http://localhost:9501/users/${id}`);
    for (let id of data.itemIds) http.del(`http://localhost:9501/items/${id}`);
}
