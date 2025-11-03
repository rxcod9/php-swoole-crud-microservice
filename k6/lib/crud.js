/**
 * @file lib/crud.js
 * @description Implements generic CRUD execution logic per entity with reduced complexity and parameter grouping.
 */

import http from 'k6/http';
import { ENV } from './env.js';
import { recordTrendAndCheck, secureRandomInt } from './utils.js';

/**
 * Helper: Selects an entity ID based on hot/cool/available sets.
 * @param {string[]} hotIds
 * @param {string[]} vuIds
 * @param {string[]} coolIds
 * @param {boolean} preferHot
 * @returns {string|null}
 */
function selectTargetId(hotIds, vuIds, coolIds, preferHot = true) {
    const pool = preferHot && hotIds.length ? hotIds : vuIds;
    if (!pool.length) return null;

    for (let i = 0; i < 3; i++) { // try a few times
        const id = pool[secureRandomInt(0, pool.length)];
        if (!coolIds.includes(id)) return id;
    }

    // fallback to any id
    // return vuIds.length ? vuIds[secureRandomInt(0, vuIds.length)] : null;
    return null;
}

/**
 * Executes a CRUD HTTP request for the given operation.
 * Reduced parameters by grouping logically related args into objects.
 *
 * @param {string} op
 * @param {string} baseUrl
 * @param {string} entity
 * @param {{ generateFn: Function, trends: Record<string, import('k6/metrics').Trend>, contentType: string }} context
 * @param {{ vuIds: string[], hotIds: string[], coolIds: string[] }} idSets
 */
function executeCrudOp(op, baseUrl, entity, context, idSets) {
    switch (op) {
        case 'list': {
            executeList(baseUrl, entity, context);
            break;
        }
        case 'read': {
            executeRead(baseUrl, entity, context, idSets);
            break;
        }
        case 'create': {
            executeCreate(baseUrl, entity, context, idSets);
            break;
        }
        case 'update': {
            executeUpdate(baseUrl, entity, context, idSets);
            break;
        }
        case 'delete': {
            executeDelete(baseUrl, entity, context, idSets);
            break;
        }
        default:
            console.warn(`[WARN] Unsupported CRUD operation: ${op}`);
    }
}

/**
 * Executes a CRUD HTTP request for the given operation.
 * Reduced parameters by grouping logically related args into objects.
 *
 * @param {string} baseUrl
 * @param {string} entity
 * @param {{ generateFn: Function, trends: Record<string, import('k6/metrics').Trend>, contentType: string }} context
 */
function executeList(baseUrl, entity, context) {
    const { trends } = context;

    const res = http.get(baseUrl);
    recordTrendAndCheck(res, entity, "list", trends.list, 200);
}

/**
 * Executes a CRUD HTTP request for the given operation.
 * Reduced parameters by grouping logically related args into objects.
 *
 * @param {string} baseUrl
 * @param {string} entity
 * @param {{ generateFn: Function, trends: Record<string, import('k6/metrics').Trend>, contentType: string }} context
 * @param {{ vuIds: string[], hotIds: string[], coolIds: string[] }} idSets
 */
function executeRead(baseUrl, entity, context, idSets) {
    const { trends } = context;
    const { vuIds, hotIds, coolIds } = idSets;

    const id = selectTargetId(hotIds, vuIds, coolIds, true);
    if (!id) {
        console.log("Skipping read no id");
        return;
    }
    const res = http.get(`${baseUrl}/${id}`);
    recordTrendAndCheck(res, entity, "read", trends.read, 200);
}

/**
 * Executes a CRUD HTTP request for the given operation.
 * Reduced parameters by grouping logically related args into objects.
 *
 * @param {string} baseUrl
 * @param {string} entity
 * @param {{ generateFn: Function, trends: Record<string, import('k6/metrics').Trend>, contentType: string }} context
 * @param {{ vuIds: string[], hotIds: string[], coolIds: string[] }} idSets
 */
function executeCreate(baseUrl, entity, context, idSets) {
    const { generateFn, trends, contentType = 'json' } = context;
    const { vuIds } = idSets;

    const obj = generateFn(secureRandomInt(0, 1000000));

    // choose encoding based on contentType
    let body, headers;
    if (contentType === 'form') {
        body = encodeFormData(obj);
        headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    } else {
        body = JSON.stringify(obj);
        headers = { 'Content-Type': 'application/json' };
    }

    const res = http.post(baseUrl, body, { headers });
    recordTrendAndCheck(res, entity, "create", trends.create, [200, 201, 202]);

    if (res.status === 201) {
        try {
            const parsed = JSON.parse(res.body);
            if (parsed?.id) vuIds.push(parsed.id);
        } catch {
            // ignore malformed response
        }
    }
}

/**
 * Executes a CRUD HTTP request for the given operation.
 * Reduced parameters by grouping logically related args into objects.
 *
 * @param {string} baseUrl
 * @param {string} entity
 * @param {{ generateFn: Function, trends: Record<string, import('k6/metrics').Trend>, contentType: string }} context
 * @param {{ vuIds: string[], hotIds: string[], coolIds: string[] }} idSets
 */
function executeUpdate(baseUrl, entity, context, idSets) {
    const { generateFn, trends, contentType = 'json' } = context;
    const { vuIds, hotIds, coolIds } = idSets;

    const id = selectTargetId(hotIds, vuIds, coolIds, false);
    if (!id) {
        console.log("Skipping update no id");
        return;
    }
    const obj = generateFn(id);

    // choose encoding based on contentType
    let body, headers;
    if (contentType === 'form') {
        body = encodeFormData(obj);
        headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
    } else {
        body = JSON.stringify(obj);
        headers = { 'Content-Type': 'application/json' };
    }

    const res = http.put(`${baseUrl}/${id}`, body, { headers });
    recordTrendAndCheck(res, entity, "update", trends.update, [200, 202]);
}

/**
 * Executes a CRUD HTTP request for the given operation.
 * Reduced parameters by grouping logically related args into objects.
 *
 * @param {string} baseUrl
 * @param {string} entity
 * @param {{ generateFn: Function, trends: Record<string, import('k6/metrics').Trend>, contentType: string }} context
 * @param {{ vuIds: string[], hotIds: string[], coolIds: string[] }} idSets
 */
function executeDelete(baseUrl, entity, context, idSets) {
    const { trends } = context;
    const { coolIds } = idSets;

    const id = coolIds[secureRandomInt(0, coolIds.length)];
    if (!id) {
        console.log("Skipping delete no id");
        return;
    }
    const res = http.del(`${baseUrl}/${id}`);
    recordTrendAndCheck(res, entity, "delete", trends.delete, [200, 202, 204]);
}

/**
 * Performs a single CRUD operation based on allowedOps from ENV.
 *
 * @param {object} params
 * @param {string[]} params.vuIds
 * @param {string[]} params.hotIds
 * @param {string[]} params.coolIds
 * @param {string} params.entity
 * @param {Function} params.generateFn
 * @param {Record<string, import('k6/metrics').Trend>} params.trends
 * @param {string[]} params.allowedOps
 * @param {bool} params.async
 * @returns {void}
 */
export function performCrudAction({
    vuIds,
    hotIds,
    coolIds,
    entity,
    generateFn,
    trends,
    allowedOps,
    contentType = 'json' // can be 'json' or 'form'
}) {
    const op = allowedOps[secureRandomInt(0, allowedOps.length)];
    const baseUrl = `${ENV.BASE_URL}/${entity}`;

    const idSets = { vuIds, hotIds, coolIds };
    const context = { generateFn, trends, contentType };

    executeCrudOp(op, baseUrl, entity, context, idSets);
}
