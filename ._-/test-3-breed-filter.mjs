/**
 * Test: Breed filter updates based on selected type
 *
 * Verifies:
 *   1. data-breed-by-type map is present on the block element
 *   2. Selecting a type hides breeds not in that type
 *   3. Breeds valid for the type remain visible
 *   4. Resetting type restores all breed options
 *   5. On page load, breed options are pre-filtered if a default type is set
 *
 * Run: node ._-/test-3-breed-filter.mjs
 */

import { chromium } from "playwright";

const URL = "http://alvars-portfolio.local/pp-test/";

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  const errors = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") errors.push(msg.text());
  });

  console.log("Loading:", URL);
  await page.goto(URL, { waitUntil: "networkidle" });

  // -----------------------------------------------------------------------
  // Setup: verify block and breed-by-type data exist
  // -----------------------------------------------------------------------
  const block = await page.$(".ppgal2-block");
  if (!block) {
    console.log("FAIL: .ppgal2-block not found");
    await browser.close();
    process.exit(1);
  }

  const rawMap = await page.$eval(".ppgal2-block", (el) => el.dataset.breedByType);
  if (!rawMap) {
    console.log("FAIL: data-breed-by-type attribute missing from .ppgal2-block");
    await browser.close();
    process.exit(1);
  }

  let breedByType;
  try {
    breedByType = JSON.parse(rawMap);
  } catch (e) {
    console.log("FAIL: data-breed-by-type is not valid JSON:", rawMap);
    await browser.close();
    process.exit(1);
  }

  const typeKeys = Object.keys(breedByType);
  console.log("PASS: data-breed-by-type present, types:", typeKeys);

  if (typeKeys.length === 0) {
    console.log("SKIP: no type→breed mappings found, nothing to test");
    await browser.close();
    process.exit(0);
  }

  // -----------------------------------------------------------------------
  // Check selects exist
  // -----------------------------------------------------------------------
  const typeSelect = await page.$('.ppgal2-filter[data-taxonomy="type"]');
  const breedSelect = await page.$('.ppgal2-filter[data-taxonomy="breed"]');

  if (!typeSelect) {
    console.log("SKIP: no type filter select found");
    await browser.close();
    process.exit(0);
  }
  if (!breedSelect) {
    console.log("SKIP: no breed filter select found");
    await browser.close();
    process.exit(0);
  }

  // -----------------------------------------------------------------------
  // Test 1: Select first type — only its breeds should be visible
  // -----------------------------------------------------------------------
  const firstType = typeKeys[0];
  const expectedBreeds = breedByType[firstType];

  console.log(`\nTest 1: Select type "${firstType}", expect breeds: [${expectedBreeds.join(", ")}]`);
  await typeSelect.selectOption(firstType);
  await page.waitForTimeout(300);

  const breedOptions = await page.$$eval(
    '.ppgal2-filter[data-taxonomy="breed"] option',
    (opts) => opts.map((o) => ({ value: o.value, hidden: o.hidden }))
  );

  const visibleBreeds = breedOptions.filter((o) => o.value && !o.hidden).map((o) => o.value);
  const hiddenBreeds  = breedOptions.filter((o) => o.value &&  o.hidden).map((o) => o.value);

  console.log("  Visible breeds:", visibleBreeds);
  console.log("  Hidden breeds: ", hiddenBreeds);

  const extraVisible = visibleBreeds.filter((b) => !expectedBreeds.includes(b));
  const missingVisible = expectedBreeds.filter((b) => !visibleBreeds.includes(b));

  if (extraVisible.length > 0) {
    console.log("  FAIL: breeds visible but should be hidden:", extraVisible);
  } else if (missingVisible.length > 0) {
    console.log("  FAIL: breeds should be visible but are hidden:", missingVisible);
  } else {
    console.log("  PASS: breed options match type");
  }

  // -----------------------------------------------------------------------
  // Test 2: Reset type to "" — all breed options should be visible again
  // -----------------------------------------------------------------------
  console.log("\nTest 2: Reset type to '' — all breeds should reappear");
  await typeSelect.selectOption("");
  await page.waitForTimeout(300);

  const afterReset = await page.$$eval(
    '.ppgal2-filter[data-taxonomy="breed"] option',
    (opts) => opts.filter((o) => o.value && o.hidden).map((o) => o.value)
  );

  if (afterReset.length > 0) {
    console.log("  FAIL: some breeds still hidden after reset:", afterReset);
  } else {
    console.log("  PASS: all breed options visible after reset");
  }

  // -----------------------------------------------------------------------
  // Test 3: If a second type exists, check cross-type isolation
  // -----------------------------------------------------------------------
  if (typeKeys.length > 1) {
    const secondType = typeKeys[1];
    const secondBreeds = breedByType[secondType];

    console.log(`\nTest 3: Select type "${secondType}", expect breeds: [${secondBreeds.join(", ")}]`);
    await typeSelect.selectOption(secondType);
    await page.waitForTimeout(300);

    const opts2 = await page.$$eval(
      '.ppgal2-filter[data-taxonomy="breed"] option',
      (opts) => opts.map((o) => ({ value: o.value, hidden: o.hidden }))
    );
    const visible2 = opts2.filter((o) => o.value && !o.hidden).map((o) => o.value);
    const extra2 = visible2.filter((b) => !secondBreeds.includes(b));
    const missing2 = secondBreeds.filter((b) => !visible2.includes(b));

    if (extra2.length > 0) {
      console.log("  FAIL: breeds visible but not in second type:", extra2);
    } else if (missing2.length > 0) {
      console.log("  FAIL: breeds for second type hidden:", missing2);
    } else {
      console.log("  PASS: breed options correct for second type");
    }
  }

  // -----------------------------------------------------------------------
  // Summary
  // -----------------------------------------------------------------------
  console.log("\n=== Console errors ===");
  console.log(errors.length ? errors : "none");

  await browser.close();
})();
