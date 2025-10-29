/**
 * @file lib/metrics.js
 * @description Handles K6 Trend metrics and threshold configuration.
 * Metrics must be declared in the init context (outside setup/default/teardown).
 */

import { Trend } from 'k6/metrics';
import { ENV } from './env.js';

/**
 * Registry for all metrics, grouped by entity and operation.
 * Example:
 *  METRICS_REGISTRY = {
 *    users: { list: Trend, read: Trend, create: Trend },
 *    items: { list: Trend, update: Trend }
 *  }
 *
 * @constant
 * @type {Record<string, Record<string, Trend>>}
 */
export const METRICS_REGISTRY = {};

// Ensure fallbacks to safe defaults if ENV misfires.
const entities = Array.isArray(ENV.ENTITIES) ? ENV.ENTITIES : ['users', 'items'];
const crudOps = Array.isArray(ENV.CRUD) ? ENV.CRUD : ['list', 'read', 'create', 'update'];

for (const entity of entities) {
    const upper = entity.toUpperCase();
    METRICS_REGISTRY[entity] = {};

    for (const op of crudOps) {
        const opUpper = op.toUpperCase();
        const metricName = `${upper}_${opUpper}_latency_ms`;

        // Each Trend metric tracks latency for specific entity and CRUD operation.
        METRICS_REGISTRY[entity][op] = new Trend(metricName);
    }
}

/**
 * Builds dynamic K6 threshold rules based on ENV and METRICS_REGISTRY.
 * Thresholds automatically adapt to all entities and CRUD ops defined in env.
 *
 * @function
 * @returns {Record<string, string[]>} Threshold configuration for k6 `options`
 */
export function buildThresholds() {
    const thresholds = {
        http_req_duration: ['p(95)<250'] // Baseline rule for all HTTP requests
    };

    // Default latency targets per CRUD operation
    const defaultRules = {
        list: ['avg<150'],
        read: ['avg<100'],
        create: ['avg<150'],
        update: ['avg<200'],
        delete: ['avg<150']
    };

    for (const entity of Object.keys(METRICS_REGISTRY)) {
        for (const op of Object.keys(METRICS_REGISTRY[entity])) {
            const metric = `${entity.toUpperCase()}_${op.toUpperCase()}_latency_ms`;
            thresholds[metric] = defaultRules[op] || ['avg<200'];
        }
    }

    return thresholds;
}
