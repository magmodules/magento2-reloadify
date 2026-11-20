/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { APIRequestContext, request as playwrightRequest } from '@playwright/test';

export const BASE_URL = (process.env.BASE_URL || 'https://magento.test/').replace(/\/?$/, '/');

export interface RestResponse<T = any> {
  status: number;
  body: T;
  text: string;
}

/**
 * A REST client for Magento's web API with an optional Bearer token.
 *
 * Every call returns status and body instead of throwing, so a spec can assert on a refusal as
 * easily as on an answer. `ok()` is the throwing variant for fixtures and happy paths.
 */
export class Rest {
  constructor(private readonly context: APIRequestContext, private readonly token?: string | null) {}

  /** A client on its own request context, for global-setup where no test fixture exists. */
  static async create(token?: string | null): Promise<Rest> {
    const context = await playwrightRequest.newContext({ baseURL: BASE_URL, ignoreHTTPSErrors: true });
    return new Rest(context, token);
  }

  /** The same context with another token (or none). */
  as(token?: string | null): Rest {
    return new Rest(this.context, token);
  }

  async call<T = any>(method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, data?: unknown): Promise<RestResponse<T>> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (this.token) {
      headers.Authorization = `Bearer ${this.token}`;
    }
    const response = await this.context.fetch(`rest/${path.replace(/^\//, '')}`, {
      method,
      headers,
      data: data === undefined ? undefined : data,
      failOnStatusCode: false,
      timeout: 120000,
    });
    const text = await response.text();
    let body: any = text;
    try {
      body = text === '' ? null : JSON.parse(text);
    } catch {
      // not JSON: keep the text
    }
    return { status: response.status(), body: body as T, text };
  }

  get<T = any>(path: string): Promise<RestResponse<T>> {
    return this.call<T>('GET', path);
  }

  post<T = any>(path: string, data?: unknown): Promise<RestResponse<T>> {
    return this.call<T>('POST', path, data ?? {});
  }

  put<T = any>(path: string, data?: unknown): Promise<RestResponse<T>> {
    return this.call<T>('PUT', path, data ?? {});
  }

  /** The body of a 2xx answer; anything else throws with the response in the message. */
  async ok<T = any>(method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, data?: unknown): Promise<T> {
    const response = await this.call<T>(method, path, data);
    if (response.status < 200 || response.status >= 300) {
      throw new Error(`${method} ${path} answered ${response.status}: ${response.text.slice(0, 2000)}`);
    }
    return response.body;
  }
}

/** A query string for searchCriteria paging, plus an optional Reloadify `filter` parameter. */
export function query(options: { pageSize?: number; currentPage?: number; filter?: object } = {}): string {
  const parts: string[] = [];
  if (options.pageSize !== undefined) {
    parts.push(`searchCriteria[pageSize]=${options.pageSize}`);
  }
  if (options.currentPage !== undefined) {
    parts.push(`searchCriteria[currentPage]=${options.currentPage}`);
  }
  if (options.filter !== undefined) {
    parts.push(`filter=${encodeURIComponent(JSON.stringify(options.filter))}`);
  }
  return parts.length ? `?${parts.join('&')}` : '';
}
