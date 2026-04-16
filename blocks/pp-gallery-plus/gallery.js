/**
 * PP Gallery Plus -- frontend JS
 *
 * Modules:
 *   1. Column selector
 *   2. Filters (Type / Breed / Tag)
 *   3. Infinite scroll
 *   4. Lightbox (vanilla JS, touch swipe, preloading)
 */

(function () {
  "use strict";

  // -----------------------------------------------------------------------
  // Shared state
  // -----------------------------------------------------------------------
  var block, gallery, ajaxUrl, perPage, showAlt, currentPage, maxPages, loading;
  var filters = {};

  // -----------------------------------------------------------------------
  // 1. Column selector
  // -----------------------------------------------------------------------

  /**
   * Creates a <select> above .pp-gallery to change column count.
   * Infers initial count from CSS-applied widths.
   */
  function setupColumns() {
    var firstItem = gallery.querySelector("li");
    if (!firstItem) return;

    var itemW = parseFloat(getComputedStyle(firstItem).width);
    var galleryW = parseFloat(getComputedStyle(gallery).width);
    var cols = Math.round(galleryW / itemW) || 5;

    var wrap = document.createElement("div");
    wrap.className = "ppgal2-column-select";

    var sel = document.createElement("select");
    sel.setAttribute("aria-label", "Columns");

    for (var i = cols; i >= 1; i--) {
      var opt = document.createElement("option");
      opt.value = i;
      opt.textContent = i + " Column" + (i > 1 ? "s" : "");
      sel.appendChild(opt);
    }

    sel.addEventListener("change", function () {
      var w = 100 / parseInt(this.value, 10) + "%";
      gallery.querySelectorAll("li").forEach(function (li) {
        li.style.width = w;
      });
    });

    wrap.appendChild(sel);
    gallery.before(wrap);
  }

  // -----------------------------------------------------------------------
  // 2. Filters
  // -----------------------------------------------------------------------

  /**
   * Bind change handlers on each .ppgal2-filter <select>.
   * On change, clears the gallery and reloads from page 1.
   */
  function setupFilters() {
    var selects = block.querySelectorAll(".ppgal2-filter");
    selects.forEach(function (sel) {
      sel.addEventListener("change", function () {
        filters[this.dataset.taxonomy] = this.value;
        reloadGallery();
      });
    });
  }

  /**
   * Clear current items, reset to page 1, fetch filtered results.
   */
  function reloadGallery() {
    currentPage = 0;
    gallery.innerHTML = "";
    showSentinel(true);
    fetchPage();
  }

  // -----------------------------------------------------------------------
  // 3. Infinite scroll
  // -----------------------------------------------------------------------

  /**
   * Set up an IntersectionObserver on the sentinel element.
   * Triggers fetchPage() when the sentinel scrolls into view.
   */
  function setupInfiniteScroll() {
    var sentinel = block.querySelector(".ppgal2-sentinel");
    if (!sentinel) return;

    var observer = new IntersectionObserver(
      function (entries) {
        if (entries[0].isIntersecting && !loading && currentPage < maxPages) {
          fetchPage();
        }
      },
      { rootMargin: "200px" }
    );

    observer.observe(sentinel);
  }

  /**
   * Fetch the next page of gallery items via AJAX and append to the grid.
   */
  function fetchPage() {
    loading = true;
    currentPage++;

    var params = new URLSearchParams({
      action: "ppgal2_load_more",
      page: currentPage,
      per_page: perPage,
      show_alt_thumbs: showAlt ? "1" : "",
    });

    Object.keys(filters).forEach(function (key) {
      if (filters[key]) params.set(key, filters[key]);
    });

    fetch(ajaxUrl + "?" + params.toString())
      .then(function (r) {
        return r.json();
      })
      .then(function (resp) {
        if (resp.success && resp.data.html) {
          gallery.insertAdjacentHTML("beforeend", resp.data.html);
          maxPages = resp.data.max_pages;
          bindThumbnailClicks();
        }

        if (!resp.data.has_more) {
          showSentinel(false);
        }
      })
      .catch(function (err) {
        console.error("ppgal2 load error:", err);
      })
      .finally(function () {
        loading = false;
      });
  }

  /**
   * Show or hide the infinite scroll sentinel.
   *
   * @param {boolean} visible Whether to show the sentinel.
   */
  function showSentinel(visible) {
    var el = block.querySelector(".ppgal2-sentinel");
    if (el) el.style.display = visible ? "" : "none";
  }

  // -----------------------------------------------------------------------
  // 4. Lightbox
  // -----------------------------------------------------------------------

  var lightbox, lbImage, lbTitle, lbDesc, lbSpinner;
  var posts = [];
  var currentIndex = 0;

  /**
   * Initialize lightbox: bind close/prev/next, keyboard, touch swipe.
   */
  function setupLightbox() {
    lightbox = block.querySelector(".ppgal2-lightbox");
    lbImage = lightbox.querySelector("#lightbox-image");
    lbTitle = lightbox.querySelector("#lightbox-title");
    lbDesc = lightbox.querySelector("#lightbox-description");
    lbSpinner = lightbox.querySelector(".ppgal2-lightbox-spinner");

    lightbox.querySelector(".close").addEventListener("click", closeLightbox);
    lightbox.querySelector(".prev").addEventListener("click", function (e) {
      e.preventDefault();
      navigate(-1);
    });
    lightbox.querySelector(".next").addEventListener("click", function (e) {
      e.preventDefault();
      navigate(1);
    });

    // Close on backdrop click (not on inner content)
    lightbox.addEventListener("click", function (e) {
      if (
        e.target === lightbox ||
        e.target === lightbox.querySelector(".lightbox-content")
      ) {
        closeLightbox();
      }
    });

    // Keyboard navigation
    document.addEventListener("keydown", function (e) {
      if (lightbox.style.display === "none") return;
      switch (e.key) {
        case "Escape":
          closeLightbox();
          break;
        case "ArrowLeft":
          navigate(-1);
          break;
        case "ArrowRight":
          navigate(1);
          break;
      }
    });

    // Touch swipe support
    var touchStartX = 0;
    lightbox.addEventListener(
      "touchstart",
      function (e) {
        touchStartX = e.changedTouches[0].screenX;
      },
      { passive: true }
    );

    lightbox.addEventListener(
      "touchend",
      function (e) {
        var diff = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(diff) > 50) {
          navigate(diff > 0 ? -1 : 1);
        }
      },
      { passive: true }
    );

    bindThumbnailClicks();
  }

  /**
   * Bind click handlers on all .post_thumbnail links.
   * Safe to call repeatedly -- skips already-bound links.
   */
  function bindThumbnailClicks() {
    posts = Array.from(gallery.querySelectorAll(".post_thumbnail"));
    posts.forEach(function (link) {
      if (link.dataset.lbBound) return;
      link.dataset.lbBound = "1";

      link.addEventListener("click", function (e) {
        e.preventDefault();
        currentIndex = Array.from(
          gallery.querySelectorAll(".post_thumbnail")
        ).indexOf(link);
        openLightbox(currentIndex);
      });
    });
  }

  /**
   * Open the lightbox for a given gallery item index.
   *
   * @param {number} idx Index into the current posts list.
   */
  function openLightbox(idx) {
    posts = Array.from(gallery.querySelectorAll(".post_thumbnail"));
    currentIndex = idx;
    var link = posts[idx];
    if (!link) return;

    var postId = link.dataset.postId;
    lbSpinner.style.display = "";
    lbImage.style.opacity = "0.3";
    lightbox.style.display = "flex";
    document.body.style.overflow = "hidden";

    // Store previous focus for restoration on close
    lightbox._prevFocus = document.activeElement;
    lightbox.querySelector(".close").focus();

    fetchPostData(postId);
    preloadAdjacent(idx);
  }

  /**
   * Close the lightbox and restore scroll + focus.
   */
  function closeLightbox() {
    lightbox.style.display = "none";
    document.body.style.overflow = "";
    if (lightbox._prevFocus) lightbox._prevFocus.focus();
  }

  /**
   * Navigate the lightbox by offset, wrapping around edges.
   *
   * @param {number} dir Direction offset (-1 = prev, +1 = next).
   */
  function navigate(dir) {
    posts = Array.from(gallery.querySelectorAll(".post_thumbnail"));
    currentIndex = (currentIndex + dir + posts.length) % posts.length;
    openLightbox(currentIndex);
  }

  /**
   * Fetch full post data for the lightbox via AJAX.
   *
   * @param {string} postId WordPress post ID.
   */
  function fetchPostData(postId) {
    var body = new FormData();
    body.append("action", "get_post_data");
    body.append("id", postId);

    fetch(ajaxUrl, { method: "POST", body: body })
      .then(function (r) {
        return r.json();
      })
      .then(function (resp) {
        if (resp.success) {
          lbTitle.textContent = resp.data.title;
          lbDesc.innerHTML = resp.data.description;
          lbImage.alt = resp.data.title;

          // Load image in background, then reveal with transition
          var img = new Image();
          img.onload = function () {
            lbImage.src = resp.data.image_url;
            lbImage.style.opacity = "1";
            lbSpinner.style.display = "none";
          };
          img.src = resp.data.image_url;
        }
      })
      .catch(function () {
        lbSpinner.style.display = "none";
        lbImage.style.opacity = "1";
      });
  }

  /**
   * Preload thumbnail images for items adjacent to the current index.
   * Helps make prev/next navigation feel instant.
   *
   * @param {number} idx Current lightbox index.
   */
  function preloadAdjacent(idx) {
    [-1, 1].forEach(function (offset) {
      var neighbor = posts[(idx + offset + posts.length) % posts.length];
      if (neighbor) {
        var src = neighbor.querySelector("img");
        if (src) new Image().src = src.getAttribute("src");
      }
    });
  }

  // -----------------------------------------------------------------------
  // Init
  // -----------------------------------------------------------------------

  document.addEventListener("DOMContentLoaded", function () {
    block = document.querySelector(".ppgal2-block");
    if (!block) return;

    gallery = block.querySelector(".pp-gallery");
    if (!gallery) return;

    ajaxUrl =
      typeof ppgal2Front !== "undefined"
        ? ppgal2Front.ajaxUrl
        : "/wp-admin/admin-ajax.php";
    perPage = parseInt(block.dataset.perPage, 10) || 20;
    showAlt = block.dataset.showAlt === "1";
    currentPage = 1;
    maxPages = parseInt(block.dataset.maxPages, 10) || 1;
    loading = false;

    setupColumns();
    setupFilters();
    setupInfiniteScroll();
    setupLightbox();
  });
})();
