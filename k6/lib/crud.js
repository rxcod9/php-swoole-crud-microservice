/**
 * @file lib/crud.js
 * @description Implements generic CRUD execution logic per entity.
 */

import http from 'k6/http';
import { check } from 'k6';
import { ENV } from './env.js';
import { secureRandomInt } from './utils.js';

/**
 * Performs a single CRUD operation based on allowedOps from ENV.
 *
 * @param {object} params
 * @param {string[]} params.vuIds - IDs accessible to this VU
 * @param {string[]} params.hotIds - Hot IDs for read-heavy or update-heavy ops
 * @param {string[]} params.coolIds - Cool IDs to skip for certain ops
 * @param {string} params.entity - Entity name (e.g., users, items)
 * @param {Function} params.generateFn - Function to generate new entity payloads
 * @param {Record<string, import('k6/metrics').Trend>} params.trends - Trend metrics for the entity
 * @param {string[]} params.allowedOps - Allowed operations (e.g., list,read,create,update,delete)
 * @returns {void}
 */
export function performCrudAction({
    vuIds,
    hotIds,
    coolIds,
    entity,
    generateFn,
    trends,
    allowedOps
}) {
    // Choose random operation based on allowedOps
    const op = allowedOps[secureRandomInt(0, allowedOps.length)];
    const baseUrl = `${ENV.BASE_URL}/${entity}`;

    switch (op) {
        case 'list': {
            const res = http.get(`${baseUrl}`);
            trends.list?.add(res.timings.duration);
            check(res, { [`${entity} LIST success`]: r => r.status === 200 });
            break;
        }

        case 'read': {
            const id = hotIds.length
                ? hotIds[secureRandomInt(0, hotIds.length)]
                : vuIds[secureRandomInt(0, vuIds.length)];
            if (!id || coolIds.includes(id)) return;
            const res = http.get(`${baseUrl}/${id}`);
            trends.read?.add(res.timings.duration);
            check(res, { [`${entity} READ success`]: r => r.status === 200 });
            break;
        }

        case 'create': {
            const obj = generateFn(secureRandomInt(0, 1000000));
            const res = http.post(baseUrl, JSON.stringify(obj), {
                headers: { 'Content-Type': 'application/json' }
            });
            trends.create?.add(res.timings.duration);
            check(res, { [`${entity} CREATE success`]: r => r.status === 201 });
            if (res.status === 201) {
                try {
                    const parsed = JSON.parse(res.body);
                    if (parsed?.id) vuIds.push(parsed.id);
                } catch { }
            }
            break;
        }

        case 'update': {
            const id = hotIds.length
                ? hotIds[secureRandomInt(0, hotIds.length)]
                : vuIds[secureRandomInt(0, vuIds.length)];
            if (!id || coolIds.includes(id)) return;
            const obj = generateFn(id);
            const res = http.put(`${baseUrl}/${id}`, JSON.stringify(obj), {
                headers: { 'Content-Type': 'application/json' }
            });
            trends.update?.add(res.timings.duration);
            check(res, { [`${entity} UPDATE success`]: r => r.status === 200 });
            break;
        }

        case 'delete': {
            const id = coolIds[secureRandomInt(0, coolIds.length)];
            if (!id) return;
            const res = http.del(`${baseUrl}/${id}`);
            trends.delete?.add(res.timings.duration);
            check(res, { [`${entity} DELETE success`]: r => [200, 204].includes(r.status) });
            break;
        }

        default:
            console.warn(`[WARN] Unsupported CRUD operation: ${op}`);
    }
}
