#!/usr/bin/env node

/**
 * release.js
 * A script to handle release tasks for the TapTree Payments plugin.
 *
 * Purpose:
 * - Update version numbers in critical files (`taptree-payments-for-woocommerce.php` and `readme.txt`).
 * - Create a release zip archive while excluding files and directories listed in `.distignore`.
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const {Command} = require('commander');
const ignore = require('ignore');
const packageJsonPath = path.resolve(__dirname, '../package.json');
const packageJson = require(packageJsonPath);

const WORKING_DIR = path.resolve(__dirname, '..'); // Plugin directory
const DISTIGNORE_FILE = path.join(WORKING_DIR, '.distignore');
const OUTPUT_DIR = path.resolve(WORKING_DIR, '..'); // Parent directory for the release zip

const program = new Command();

/**
 * Update version numbers in key files.
 */
function updateVersions(version) {
  const pluginFile = path.resolve(
    WORKING_DIR,
    'taptree-payments-for-woocommerce.php'
  );
  const readmeFile = path.resolve(WORKING_DIR, 'readme.txt');

  const filesToUpdate = [pluginFile, readmeFile];

  filesToUpdate.forEach((file) => {
    if (!fs.existsSync(file)) {
      console.error(`Error: ${file} not found.`);
      return;
    }

    let content = fs.readFileSync(file, 'utf8');
    content = content.replace(/(Version:\s*)([\d.]+)/, `$1${version}`);
    content = content.replace(/(Stable tag:\s*)([\d.]+)/, `$1${version}`);

    fs.writeFileSync(file, content, 'utf8');
    console.log(`Updated version in ${file} to ${version}`);
  });

  // Update package.json version
  packageJson.version = version;
  fs.writeFileSync(
    packageJsonPath,
    JSON.stringify(packageJson, null, 2),
    'utf8'
  );
  console.log(`Updated version in package.json to ${version}`);
}

/**
 * Create the release zip.
 */
function createReleaseZip(version) {
  const outputFileName = `taptree-payments-for-woocommerce-${version}.zip`;
  const outputFilePath = path.join(OUTPUT_DIR, outputFileName);
  const output = fs.createWriteStream(outputFilePath);
  const archive = archiver('zip', {zlib: {level: 9}});

  output.on('close', () => {
    console.log(
      `Release zip created in parent directory: ${outputFilePath} (${archive.pointer()} bytes)`
    );
  });

  archive.on('error', (err) => {
    throw err;
  });

  archive.pipe(output);

  const ig = ignore();

  // Load exclusions from .distignore
  if (fs.existsSync(DISTIGNORE_FILE)) {
    const ignoreContent = fs.readFileSync(DISTIGNORE_FILE, 'utf8');
    ig.add(ignoreContent);
  } else {
    console.error(`Error: ${DISTIGNORE_FILE} not found.`);
    process.exit(1);
  }

  // Add files and folders to archive, respecting .distignore
  const filesToAdd = [];
  function collectFiles(dir) {
    fs.readdirSync(dir).forEach((file) => {
      const fullPath = path.join(dir, file);
      const relativePath = path.relative(WORKING_DIR, fullPath);

      if (ig.ignores(relativePath)) {
        return;
      }

      if (fs.lstatSync(fullPath).isDirectory()) {
        collectFiles(fullPath); // Recursively collect files in subdirectories
      } else {
        filesToAdd.push({fullPath, relativePath});
      }
    });
  }

  collectFiles(WORKING_DIR);

  filesToAdd.forEach(({fullPath, relativePath}) => {
    archive.file(fullPath, {name: relativePath});
  });

  archive.finalize();
}

/**
 * Main script.
 */
program
  .version(packageJson.version)
  .description('Release script for TapTree Payments for WooCommerce');

program
  .command('sync-version [version]')
  .description(
    'Update the version in key plugin files. If no version is provided, use the current version from package.json.'
  )
  .action((version) => {
    const finalVersion = version || packageJson.version;
    console.log(`Syncing version to: ${finalVersion}`);
    updateVersions(finalVersion);
  });

program
  .command('build-zip')
  .description('Build the release zip file.')
  .action(() => {
    console.log(`Creating release zip for version: ${packageJson.version}`);
    createReleaseZip(packageJson.version);
  });

program.parse(process.argv);
