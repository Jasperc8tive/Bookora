/**
 * Shared types for the Bookora admin SPA.
 */

export interface BookoraAdminGlobal {
  restUrl: string;
  nonce: string;
  version: string;
}

export interface SystemHealth {
  plugin: {
    name: string;
    version: string;
    db_version: string;
  };
  env: {
    php: string;
    wp: string;
    prefix: string;
  };
  database: {
    migrated: boolean;
    applied: string[];
    tables: Record<string, boolean>;
  };
  healthy: boolean;
  timestamp: string;
}

export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
}

declare global {
  interface Window {
    BookoraAdmin?: BookoraAdminGlobal;
  }
}
