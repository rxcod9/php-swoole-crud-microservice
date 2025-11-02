/**
 * @file tests/crud_main_test.js
 * @description Master entrypoint for dynamic, environment-driven K6 load testing.
 * Supports multiple entities, CRUD control, thresholds, and teardown.
 */

import http from 'k6/http';
import { sleep } from 'k6';
import { ENV, printUsage } from '../lib/env.js';
import { generateUser, generateItem, postEntity, slicePercent, getEntities, getEntity } from '../lib/utils.js';
import { METRICS_REGISTRY, buildThresholds } from '../lib/metrics.js';
import { performCrudAction } from '../lib/crud.js';

let perVuIds = {};
let execCount = 0;
const generators = { "async-users": generateUser, users: generateUser, items: generateItem };

/**
 * Setup phase: creates initial entities for each entity type.
 * Runs once before all VUs start.
 *
 * @returns {Record<string, { ids: string[], hot: string[], cool: string[] }>}
 */
export function setup() {
    printUsage();
    const setupData = {};

    for (const entity of ENV.ENTITIES) {
        const generateFn = generators[entity];
        if(!generateFn) {
            console.error("generators not found for " + entity);
            continue;
        }
        setupData[entity] = setupEntity(entity, generateFn);
    }

    return setupData;
}

/**
 * Creates initial entity for each entity type.
 *
 * @returns { ids: string[], hot: string[], cool: string[] }
 */
function setupEntity(entity, generateFn) {
    const trendCreate = METRICS_REGISTRY[entity]?.create;
    const trendList = METRICS_REGISTRY[entity]?.list;
    const trendRead = METRICS_REGISTRY[entity]?.read;
    const ids = [];

    for (let i = 0; i < ENV.TOTAL_ENTITIES; i++) {
        postEntity(entity, `${ENV.BASE_URL}/${entity}`, generateFn(i), trendCreate, 204);
    }

    // Sleep for few seconds to cought up with async operations completions
    sleep(10); // sleeps 30 second
    
    // Now fetch lists
    for (let i = 1; i <= 10; i++) {
        const response = getEntities(entity, `${ENV.BASE_URL}/${entity}?page=${i}&limiit=100&sortDirection=DESC`, trendList);
        const records = response.data;
        console.log("records", typeof records);
        if (Array.isArray(records)) {
            for (const rec of records) {
                const id = rec?.id ?? rec?._id ?? null;
                if (id && !ids.includes(id)) {
                    ids.push(id); // push safely, prevent duplicates
                }
            }
        }
    }

    const hotIds = slicePercent(ids.slice(0, ids.length / 2), ENV.HOT_PERCENT);
    const coolIds = slicePercent(ids.slice(ids.length / 2), ENV.COOL_PERCENT);
    console.log('ids', ids);
    console.log('hotIds', hotIds);
    console.log('coolIds', coolIds);

    // Warm List Cache
    for (let i = 1; i <= 3; i++) {
        getEntities(entity, `${ENV.BASE_URL}/${entity}?page=${i}&sortDirection=DESC`, trendList);
    }

    // Warm Read Cache
    for (const hotId of hotIds) {   
        getEntity(entity, `${ENV.BASE_URL}/${entity}/${hotId}`, trendRead);
    }

    return {
        ids,
        hot: hotIds,
        cool: coolIds,
    };
}

/**
 * K6 test configuration.
 */
export const options = {
    setupTimeout: ENV.MAX_DURATION,
    teardownTimeout: ENV.MAX_DURATION,
    stages: [
        { duration: '30s', target: Math.floor(ENV.MAX_VUS / 2) },
        { duration: '1m', target: ENV.MAX_VUS },
        { duration: '30s', target: Math.floor(ENV.MAX_VUS / 2) }
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
        const generateFn = generators[entity];
        if(!generateFn) {
            console.error("generators not found for " + entity);
            continue;
        }

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
