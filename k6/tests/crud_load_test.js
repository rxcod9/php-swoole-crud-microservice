/**
 * @file tests/crud_main_test.js
 * @description Master entrypoint for dynamic, environment-driven K6 load testing.
 * Supports multiple entities, CRUD control, thresholds, and teardown.
 */

import http from 'k6/http';
import { sleep } from 'k6';
import { ENV, printUsage } from '../lib/env.js';
import { generateUser, generateItem, postEntity, slicePercent } from '../lib/utils.js';
import { METRICS_REGISTRY, buildThresholds } from '../lib/metrics.js';
import { performCrudAction } from '../lib/crud.js';

let perVuIds = {};
let execCount = 0;

/**
 * Setup phase: creates initial entities for each entity type.
 * Runs once before all VUs start.
 *
 * @returns {Record<string, { ids: string[], hot: string[], cool: string[] }>}
 */
export function setup() {
    printUsage();
    const generators = { users: generateUser, items: generateItem };
    const setupData = {};

    for (const entity of ENV.ENTITIES) {
        const genFn = generators[entity] || generateItem;
        const trend = METRICS_REGISTRY[entity]?.create;
        const ids = [];

        for (let i = 0; i < ENV.TOTAL_ENTITIES; i++) {
            const id = postEntity(`${ENV.BASE_URL}/${entity}`, genFn(i), trend);
            if (id) ids.push(id);
        }

        setupData[entity] = {
            ids,
            hot: slicePercent(ids, ENV.HOT_PERCENT),
            cool: slicePercent(ids, ENV.COOL_PERCENT)
        };
    }

    return setupData;
}

/**
 * K6 test configuration.
 */
export const options = {
    setupTimeout: ENV.MAX_DURATION,
    teardownTimeout: ENV.MAX_DURATION,
    stages: [
        { duration: '5s', target: Math.floor(ENV.MAX_VUS / 2) },
        { duration: '10s', target: ENV.MAX_VUS },
        { duration: '5s', target: 0 }
    ],
    thresholds: buildThresholds()
};

/**
 * Default execution per VU.
 * Each iteration executes a random CRUD op for all configured entities.
 *
 * @param {Record<string, { ids: string[], hot: string[], cool: string[] }>} data
 */
export default function (data) {
    if (execCount++ >= ENV.TOTAL_EXECUTIONS) return;

    for (const entity of ENV.ENTITIES) {
        if (!perVuIds[entity]) perVuIds[entity] = data[entity].ids.slice();
        const trends = METRICS_REGISTRY[entity];
        const generateFn = entity === 'users' ? generateUser : generateItem;

        performCrudAction({
            vuIds: perVuIds[entity],
            hotIds: data[entity].hot,
            coolIds: data[entity].cool,
            entity,
            generateFn,
            trends,
            allowedOps: ENV.CRUD
        });
    }

    sleep(Math.random() * 2);
}

/**
 * Teardown phase: cleans up entities after test completion.
 *
 * @param {Record<string, { ids: string[] }>} data
 */
export function teardown(data) {
    console.log('🧹 Cleaning up entities...');
    for (const entity of ENV.ENTITIES) {
        for (const id of data[entity].ids) {
            http.del(`${ENV.BASE_URL}/${entity}/${id}`);
        }
    }
}
