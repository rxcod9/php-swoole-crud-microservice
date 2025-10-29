/**
 * @fileoverview Trend metrics shared across tests.
 * These metrics are visible in K6 outputs and Prometheus dashboards.
 */

import { Trend } from 'k6/metrics';

// User entity performance metrics
export const usersListTrend = new Trend('USERS_LIST_latency_ms');
export const usersReadTrend = new Trend('USERS_READ_latency_ms');
export const usersCreateTrend = new Trend('USERS_CREATE_latency_ms');
export const usersUpdateTrend = new Trend('USERS_UPDATE_latency_ms');

// Item entity performance metrics
export const itemsListTrend = new Trend('ITEMS_LIST_latency_ms');
export const itemsReadTrend = new Trend('ITEMS_READ_latency_ms');
export const itemsCreateTrend = new Trend('ITEMS_CREATE_latency_ms');
export const itemsUpdateTrend = new Trend('ITEMS_UPDATE_latency_ms');

// Export all for convenience
export default {
    usersListTrend,
    usersReadTrend,
    usersCreateTrend,
    usersUpdateTrend,
    itemsListTrend,
    itemsReadTrend,
    itemsCreateTrend,
    itemsUpdateTrend,
};
