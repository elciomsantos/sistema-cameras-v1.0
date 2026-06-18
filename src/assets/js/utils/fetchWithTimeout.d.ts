// Type definitions for fetchWithTimeout utility

interface FetchOptions extends RequestInit {
  timeout?: number;
}

declare function fetchWithTimeout(
  url: string,
  options?: FetchOptions
): Promise<Response>;

export default fetchWithTimeout;