import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

// --------------------
// Custom Metrics
// --------------------
let listTrend = new Trend('LIST_latency_ms');
let createTrend = new Trend('CREATE_latency_ms');
let readTrend = new Trend('READ_latency_ms');
let updateTrend = new Trend('UPDATE_latency_ms');
let deleteTrend = new Trend('DELETE_latency_ms');

// --------------------
// Load Test Options
// --------------------
export let options = {
    vus: 500,        // concurrent virtual users
    iterations: 1000, // total requests (can be adjusted)
    thresholds: {
        'http_req_duration': ['p(95)<200'], // 95% requests should be below 200ms
        'LIST_latency_ms': ['avg<100'],
        'CREATE_latency_ms': ['avg<100'],
        'READ_latency_ms': ['avg<50'],
        'UPDATE_latency_ms': ['avg<100'],
        'DELETE_latency_ms': ['avg<100']
    }
};

// --------------------
// Helper: Generate Random User
// --------------------
function generateUser() {
    let id = Math.floor(Math.random() * 1000000) + ('xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    }));
    return {
        name: `user-${id}`,
        email: `user-${id}@example.com`
    };
}

// --------------------
// Helper: Generate Random Page
// --------------------
function generatePage() {
    // random 1 to 10
    let page = Math.floor(Math.random() * 10) + 1;
    return page;
}

// --------------------
// Main Test
// --------------------
export default function () {
    // 1️⃣ LIST
    let page = generatePage();
    let listRes = http.get('http://localhost:9501/users?page=' + page);
    listTrend.add(listRes.timings.duration);
    check(listRes, {
        'LIST success': (r) => r.status === 200
    });

    // 1️⃣ CREATE
    let user = generateUser();
    let createRes = http.post('http://localhost:9501/users', JSON.stringify(user), {
        headers: { 'Content-Type': 'application/json' }
    });
    createTrend.add(createRes.timings.duration);
    check(createRes, {
        'CREATE success': (r) => r.status === 201
    });
    let id;
    try {
        id = JSON.parse(createRes.body).id;
    } catch (e) {
        console.error('Failed to parse CREATE response:', createRes.body);
        return;
    }

    // 2️⃣ READ ONE
    let readRes = http.get(`http://localhost:9501/users/${id}`);
    readTrend.add(readRes.timings.duration);
    check(readRes, { 'READ success': (r) => r.status === 200 });

    // 3️⃣ UPDATE
    let updateRes = http.put(`http://localhost:9501/users/${id}`, JSON.stringify({
        name: `${user.name}-updated`,
        email: `${user.email}`
    }), {
        headers: { 'Content-Type': 'application/json' }
    });
    updateTrend.add(updateRes.timings.duration);
    check(updateRes, { 'UPDATE success': (r) => r.status === 200 });

    // 4️⃣ DELETE
    let deleteRes = http.del(`http://localhost:9501/users/${id}`);
    deleteTrend.add(deleteRes.timings.duration);
    check(deleteRes, { 'DELETE success': (r) => r.status === 204 });

    // Optional: small sleep to simulate real users
    sleep(Math.random() * 1);
}
