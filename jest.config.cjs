/** @type {import('jest').Config} */
module.exports = {
  testEnvironment: 'jsdom',
  roots: ['<rootDir>/tests/js'],
  testMatch: ['**/*.test.ts', '**/*.test.tsx'],
  setupFilesAfterEnv: ['<rootDir>/tests/js/setup.ts'],
  transform: {
    '^.+\\.(ts|tsx)$': [
      'ts-jest',
      { tsconfig: { jsx: 'react-jsx', esModuleInterop: true, types: ['jest', '@testing-library/jest-dom', 'node'] } },
    ],
  },
  moduleNameMapper: {
    '\\.(css|less|scss)$': '<rootDir>/tests/js/styleMock.cjs',
  },
};
