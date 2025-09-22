import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

// --------------------
// CONFIGURATION VARIABLES
// --------------------
const CONFIG = {
    TOTAL_ITEMS: 3000,
    HOT_PERCENT: 0.1,          // Top 10% are hot (never deleted)
    HOT_READ_RATIO: 0.8,       // 80% of reads go to hot IDs
    LIST_PAGES: 3,
    WEIGHTS: {
        LIST: 0.5,
        READ: 0.5,
        // CREATE: 0.15,
        // UPDATE: 0.07,
        // DELETE: 0.03
    },
    CONCURRENCY: {
        MAX_VUS: 1000,
        STAGES: [
            { duration: '20s', target: 0.1 },
            { duration: '40s', target: 0.4 },
            { duration: '1m', target: 1.0 },
            { duration: '20s', target: 0 }
        ]
    }
};

// --------------------
// METRICS
// --------------------
let listTrend = new Trend('LIST_latency_ms');
let readTrend = new Trend('READ_latency_ms');
// let createTrend = new Trend('CREATE_latency_ms');
// let updateTrend = new Trend('UPDATE_latency_ms');
// let deleteTrend = new Trend('DELETE_latency_ms');

// --------------------
// OPTIONS
// --------------------
export let options = {
    stages: CONFIG.CONCURRENCY.STAGES.map(s => ({
        duration: s.duration,
        target: Math.floor(s.target * CONFIG.CONCURRENCY.MAX_VUS)
    })),
    thresholds: {
        'http_req_duration': ['p(95)<200'],
        'LIST_latency_ms': ['avg<100'],
        // 'CREATE_latency_ms': ['avg<100'],
        'READ_latency_ms': ['avg<50'],
        // 'UPDATE_latency_ms': ['avg<100'],
        // 'DELETE_latency_ms': ['avg<100']
    }
};

// --------------------
// HELPERS
// --------------------
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

function randomItem(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

// --------------------
// PER-VU ITEM IDS
// --------------------
let VU_itemIds = [];

// --------------------
// SETUP
// --------------------
export function setup() {
    let itemIds = [];

    for (let i = 0; i < CONFIG.TOTAL_ITEMS; i++) {
        const item = generateItem(i);
        let res = http.post('http://localhost:9501/items', JSON.stringify(item), {
            headers: { 'Content-Type': 'application/json' }
        });
        // createTrend.add(res.timings.duration);
        check(res, { 'CREATE success': r => r.status === 201 });
        try { itemIds.push(JSON.parse(res.body).id); }
        catch (e) { console.error('Failed parse CREATE response', res.body); }
    }

    const hotCount = Math.floor(CONFIG.TOTAL_ITEMS * CONFIG.HOT_PERCENT);
    const hotIds = itemIds.slice(0, hotCount);

    return { itemIds, hotIds };
}

// --------------------
// DEFAULT FUNCTION
// --------------------
export default function (data) {
    // Initialize per-VU copy of item IDs on first iteration
    if (VU_itemIds.length === 0) {
        VU_itemIds = data.itemIds.slice();
    }
    const itemIds = VU_itemIds;
    const hotIds = data.hotIds;
    const rand = Math.random();
    const w = CONFIG.WEIGHTS;

    if (rand < w.LIST) {
        // LIST pages 1..LIST_PAGES
        const page = Math.floor(Math.random() * CONFIG.LIST_PAGES) + 1;
        const res = http.get(`http://localhost:9501/items?page=${page}`);
        listTrend.add(res.timings.duration);
        check(res, { 'LIST success': r => r.status === 200 });

    } else if (rand < w.LIST + w.READ) {
        // READ
        let id;
        if (hotIds.length && Math.random() < CONFIG.HOT_READ_RATIO) {
            id = randomItem(hotIds); // hot read
        } else {
            id = randomItem(itemIds); // random read
        }
        const res = http.get(`http://localhost:9501/items/${id}`);
        readTrend.add(res.timings.duration);
        check(res, { 'READ success': r => r.status === 200 });

    // } else if (rand < w.LIST + w.READ + w.CREATE) {
    //     // CREATE
    //     const item = generateItem(Math.floor(Math.random() * 1000000));
    //     const res = http.post('http://localhost:9501/items', JSON.stringify(item), { headers: { 'Content-Type': 'application/json' } });
    //     createTrend.add(res.timings.duration);
    //     check(res, { 'CREATE success': r => r.status === 201 });
    //     try { itemIds.push(JSON.parse(res.body).id); }
    //     catch (e) { console.error('Failed parse CREATE response', res.body); }

    // } else if (rand < w.LIST + w.READ + w.CREATE + w.UPDATE) {
    //     // UPDATE
    //     const id = randomItem(itemIds);
    //     const item = generateItem(id);
    //     const res = http.put(`http://localhost:9501/items/${id}`, JSON.stringify({ name: `${item.name}-updated`, email: item.email }), { headers: { 'Content-Type': 'application/json' } });
    //     updateTrend.add(res.timings.duration);
    //     check(res, { 'UPDATE success': r => r.status === 200 });

    } else {
        // // DELETE (skip hot IDs)
        // const deletableIds = itemIds.filter(id => !hotIds.includes(id));
        // if (!deletableIds.length) return;
        // const id = randomItem(deletableIds);
        // const res = http.del(`http://localhost:9501/items/${id}`);
        // deleteTrend.add(res.timings.duration);
        // check(res, { 'DELETE success': r => r.status === 204 });
        // const idx = itemIds.indexOf(id); if(idx!=-1) itemIds.splice(idx,1);
    }

    sleep(Math.random() * 2);
}

// --------------------
// TEARDOWN
// --------------------
export function teardown(data) {
    console.log(`Cleaning up ${data.itemIds.length} items`);
    for (let id of data.itemIds) {
        http.del(`http://localhost:9501/items/${id}`);
    }
}
