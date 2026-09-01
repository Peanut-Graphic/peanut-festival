'use strict';

const { execFileSync } = require('node:child_process');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');

const repositoryRoot = resolve(__dirname, '..');
const expected = Object.freeze({ node: '22.22.2', npm: '10.9.7', version: '1.3.2' });
const declarationsOnly = process.argv.includes('--declarations-only');

function read(path) {
  return readFileSync(resolve(repositoryRoot, path), 'utf8');
}

function readJson(path) {
  return JSON.parse(read(path));
}

function assertEqual(actual, wanted, label) {
  if (actual !== wanted) {
    throw new Error(label + ': expected ' + wanted + ', received ' + actual);
  }
}

const packageJson = readJson('frontend/package.json');
const packageLock = readJson('frontend/package-lock.json');
const lockedRoot = packageLock.packages?.[''];

assertEqual(packageJson.version, expected.version, 'frontend/package.json version');
assertEqual(lockedRoot?.version, expected.version, 'frontend/package-lock.json version');
assertEqual(packageJson.engines?.node, expected.node, 'frontend/package.json engines.node');
assertEqual(packageJson.engines?.npm, expected.npm, 'frontend/package.json engines.npm');
assertEqual(packageJson.packageManager, 'npm@' + expected.npm, 'frontend/package.json packageManager');
assertEqual(lockedRoot?.engines?.node, expected.node, 'frontend/package-lock.json engines.node');
assertEqual(lockedRoot?.engines?.npm, expected.npm, 'frontend/package-lock.json engines.npm');
assertEqual(read('.nvmrc').trim(), expected.node, '.nvmrc');

const workflowExpectations = new Map([
  ['.github/workflows/ci.yml', 3],
  ['.github/workflows/accessibility.yml', 1],
]);
let runtimeAssertions = 0;
for (const [path, expectedPins] of workflowExpectations) {
  const workflow = read(path);
  const pins = [...workflow.matchAll(/node-version:\s*['"]?([^'"\s]+)/g)].map((match) => match[1]);
  assertEqual(pins.length, expectedPins, path + ' Node declaration count');
  pins.forEach((pin, index) => assertEqual(pin, expected.node, path + ' node-version ' + (index + 1)));
  runtimeAssertions += workflow.match(/npm --version/g)?.length ?? 0;
}
assertEqual(runtimeAssertions, 4, 'workflow npm assertion count');

if (!declarationsOnly) {
  assertEqual(process.versions.node, expected.node, 'active Node runtime');
  const npmVersion = execFileSync('npm', ['--version'], { encoding: 'utf8' }).trim();
  assertEqual(npmVersion, expected.npm, 'active npm runtime');
}

console.log(
  declarationsOnly
    ? 'Runtime declarations are pinned to Node ' + expected.node + ' and npm ' + expected.npm + '.'
    : 'Runtime contract verified on Node ' + expected.node + ' and npm ' + expected.npm + '.',
);
