/**
 * Test: Lightbox AJAX action and navigation
 *
 * Verifies that clicking a gallery thumbnail and navigating
 * uses ppgal2_get_post_data (not the old get_post_data action)
 * and doesn't trigger alert() errors.
 *
 * Run: npx playwright test ._-/test-2-lightbox.mjs
 * Or:  node ._-/test-2-lightbox.mjs
 */

import { chromium } from "playwright";

const URL = "http://alvars-portfolio.local/pp-test/";

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  // Capture all alert() calls
  const alerts = [];
  page.on("dialog", async (dialog) => {
    alerts.push(dialog.message());
    await dialog.dismiss();
  });

  // Capture AJAX requests to admin-ajax.php
  const ajaxRequests = [];
  page.on("request", (req) => {
    if (req.url().includes("admin-ajax.php")) {
      ajaxRequests.push({
        url: req.url(),
        method: req.method(),
        postData: req.postData(),
      });
    }
  });

  // Capture AJAX responses
  const ajaxResponses = [];
  page.on("response", async (res) => {
    if (res.url().includes("admin-ajax.php")) {
      try {
        const body = await res.json();
        ajaxResponses.push({ url: res.url(), status: res.status(), body });
      } catch (e) {
        ajaxResponses.push({ url: res.url(), status: res.status(), body: null });
      }
    }
  });

  // Capture console errors
  const consoleErrors = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" || msg.type() === "warning") {
      consoleErrors.push(msg.text());
    }
  });

  console.log("Loading page:", URL);
  await page.goto(URL, { waitUntil: "networkidle" });

  // Check if gallery block exists
  const block = await page.$(".ppgal2-block");
  if (!block) {
    console.log("FAIL: .ppgal2-block not found on page");
    console.log("  Page title:", await page.title());
    const html = await page.content();
    console.log("  Has pp-gallery:", html.includes("pp-gallery"));
    console.log("  Has old gallery:", html.includes("pp-gallery__image"));
    await browser.close();
    process.exit(1);
  }
  console.log("PASS: .ppgal2-block found");

  // Check for thumbnails
  const thumbs = await page.$$(".ppgal2-block .ppgal2-thumb");
  console.log("  Found", thumbs.length, "thumbnail(s)");

  if (thumbs.length === 0) {
    console.log("SKIP: No thumbnails to test lightbox with");
    await browser.close();
    process.exit(0);
  }

  // Click first thumbnail
  console.log("\nTest 1: Open lightbox on first thumbnail");
  await thumbs[0].click();
  await page.waitForTimeout(1500);

  const lightboxVisible = await page.$eval(".ppgal2-lightbox", (el) => el.style.display !== "none");
  console.log("  Lightbox visible:", lightboxVisible);
  console.log("  Alerts fired:", alerts.length, alerts.length ? alerts : "");
  console.log("  AJAX requests:", ajaxRequests.length);

  for (const req of ajaxRequests) {
    const hasOldAction = req.postData && req.postData.includes("get_post_data") && !req.postData.includes("ppgal2_get_post_data");
    const hasNewAction = req.postData && req.postData.includes("ppgal2_get_post_data");
    console.log("    action:", hasNewAction ? "ppgal2_get_post_data" : hasOldAction ? "get_post_data (OLD!)" : "other", "method:", req.method);
  }

  for (const res of ajaxResponses) {
    console.log("    response status:", res.status, "success:", res.body?.success);
    if (!res.body?.success) {
      console.log("    response data:", JSON.stringify(res.body?.data || res.body));
    }
  }

  // Test 2: Navigate to next
  if (thumbs.length > 1) {
    console.log("\nTest 2: Click next arrow");
    ajaxRequests.length = 0;
    ajaxResponses.length = 0;
    alerts.length = 0;

    const nextBtn = await page.$(".ppgal2-lightbox .ppgal2-next");
    if (nextBtn) {
      await nextBtn.click();
      await page.waitForTimeout(1500);

      console.log("  Alerts fired:", alerts.length, alerts.length ? alerts : "");
      console.log("  AJAX requests:", ajaxRequests.length);

      for (const req of ajaxRequests) {
        const hasOldAction = req.postData && req.postData.includes("get_post_data") && !req.postData.includes("ppgal2_get_post_data");
        const hasNewAction = req.postData && req.postData.includes("ppgal2_get_post_data");
        console.log("    action:", hasNewAction ? "ppgal2_get_post_data" : hasOldAction ? "get_post_data (OLD!)" : "other", "method:", req.method);
      }

      for (const res of ajaxResponses) {
        console.log("    response status:", res.status, "success:", res.body?.success);
        if (!res.body?.success) {
          console.log("    response data:", JSON.stringify(res.body?.data || res.body));
        }
      }
    }
  }

  // Test 3: Close with Escape
  console.log("\nTest 3: Close lightbox with Escape");
  await page.keyboard.press("Escape");
  await page.waitForTimeout(300);
  const lightboxHidden = await page.$eval(".ppgal2-lightbox", (el) => el.style.display === "none");
  console.log("  Lightbox hidden:", lightboxHidden);

  // Summary
  console.log("\n=== Summary ===");
  console.log("Console errors/warnings:", consoleErrors.length ? consoleErrors : "none");
  console.log("Alerts (should be 0):", alerts.length);

  const pass = alerts.length === 0 && lightboxVisible && lightboxHidden;
  console.log(pass ? "\nPASS" : "\nFAIL");

  await browser.close();
  process.exit(pass ? 0 : 1);
})();
