/**
 * PP Gallery Plus -- frontend JS
 *
 * Modules:
 *   1. Column selector
 *   2. Filters (Type / Breed / Tag) + reset button
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

  var resetBtn;

  /**
   * Bind change handlers on each .ppgal2-filter <select> and the reset button.
   */
  function setupFilters() {
    var selects = block.querySelectorAll(".ppgal2-filter");
    resetBtn = block.querySelector(".ppgal2-filter-reset");

    selects.forEach(function (sel) {
      sel.addEventListener("change", function () {
        filters[this.dataset.taxonomy] = this.value;
        updateResetButton();
        reloadGallery();
      });
    });

    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        filters = {};
        selects.forEach(function (sel) {
          sel.value = "";
        });
        updateResetButton();
        reloadGallery();
      });
    }
  }

  /**
   * Show the reset button only when at least one filter is active.
   */
  function updateResetButton() {
    if (!resetBtn) return;
    var active = Object.keys(filters).some(function (k) {
      return filters[k];
    });
    resetBtn.style.display = active ? "" : "none";
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
    var sentinel = block.querySelector(".ppgal2-sentinel");
    if (sentinel) sentinel.style.display = visible ? "" : "none";
  }

  // -----------------------------------------------------------------------
  // 4. Lightbox
  // -----------------------------------------------------------------------

  var lightbox, lbImage, lbTitle, lbDesc, lbSpinner;
  var posts = [];
  var currentIndex = 0;
  var postDataCache = {};

  /**
   * Initialize lightbox: bind close/prev/next, keyboard, touch swipe.
   */
  function setupLightbox() {
    lightbox = block.querySelector(".ppgal2-lightbox");
    if (!lightbox) return;

    lbImage = lightbox.querySelector(".ppgal2-lb-image");
    lbTitle = lightbox.querySelector(".ppgal2-lb-title");
    lbDesc = lightbox.querySelector(".ppgal2-lb-description");
    lbSpinner = lightbox.querySelector(".ppgal2-lightbox-spinner");

    lightbox.querySelector(".ppgal2-close").addEventListener("click", closeLightbox);
    lightbox.querySelector(".ppgal2-prev").addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      navigate(-1);
    });
    lightbox.querySelector(".ppgal2-next").addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
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
   * Bind click handlers on all .post_thumbnail links inside our block.
   * Safe to call repeatedly -- skips already-bound links.
   */
  function bindThumbnailClicks() {
    posts = Array.from(gallery.querySelectorAll(".ppgal2-thumb"));
    posts.forEach(function (link) {
      if (link.dataset.lbBound) return;
      link.dataset.lbBound = "1";

      link.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent old theme handlers from firing
        currentIndex = Array.from(
          gallery.querySelectorAll(".ppgal2-thumb")
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
    posts = Array.from(gallery.querySelectorAll(".ppgal2-thumb"));
    currentIndex = idx;
    var link = posts[idx];
    if (!link) return;

    var postId = link.dataset.postId;
    if (!postId) return;

    lbSpinner.style.display = "";
    lbImage.style.opacity = "0.3";
    lightbox.style.display = "flex";
    document.body.style.overflow = "hidden";

    // Store previous focus for restoration on close
    lightbox._prevFocus = document.activeElement;
    lightbox.querySelector(".ppgal2-close").focus();

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
    posts = Array.from(gallery.querySelectorAll(".ppgal2-thumb"));
    currentIndex = (currentIndex + dir + posts.length) % posts.length;
    openLightbox(currentIndex);
  }

  /**
   * Fetch post data via AJAX and return a Promise.
   * Results are cached so repeated requests for the same post are free.
   *
   * @param {string} postId WordPress post ID.
   * @return {Promise<object|null>} Resolved post data or null on failure.
   */
  function getPostData(postId) {
    if (postDataCache[postId]) {
      return Promise.resolve(postDataCache[postId]);
    }

    var body = new FormData();
    body.append("action", "ppgal2_get_post_data");
    body.append("id", postId);

    return fetch(ajaxUrl, { method: "POST", body: body })
      .then(function (r) {
        return r.json();
      })
      .then(function (resp) {
        if (resp.success) {
          postDataCache[postId] = resp.data;
          return resp.data;
        }
        console.warn("ppgal2 lightbox: failed to load post", postId, resp);
        return null;
      })
      .catch(function (err) {
        console.error("ppgal2 lightbox fetch error:", err);
        return null;
      });
  }

  /**
   * Fetch post data and render it into the lightbox.
   *
   * @param {string} postId WordPress post ID.
   */
  function fetchPostData(postId) {
    getPostData(postId).then(function (data) {
      if (data) {
        lbTitle.textContent = data.title;
        lbDesc.innerHTML = data.description;
        lbImage.alt = data.title;

        var img = new Image();
        img.onload = function () {
          lbImage.src = data.image_url;
          lbImage.style.opacity = "1";
          lbSpinner.style.display = "none";
        };
        img.onerror = function () {
          lbImage.style.opacity = "1";
          lbSpinner.style.display = "none";
        };
        img.src = data.image_url;
      } else {
        lbSpinner.style.display = "none";
        lbImage.style.opacity = "1";
      }
    });
  }

  /**
   * Prefetch post data and full-size images for items adjacent to the
   * current index. On cache hit, navigation renders instantly with no
   * AJAX wait or image decode delay.
   *
   * @param {number} idx Current lightbox index.
   */
  function preloadAdjacent(idx) {
    [-1, 1].forEach(function (offset) {
      var neighbor = posts[(idx + offset + posts.length) % posts.length];
      if (!neighbor) return;

      var neighborId = neighbor.dataset.postId;
      if (!neighborId) return;

      getPostData(neighborId).then(function (data) {
        if (data && data.image_url) {
          new Image().src = data.image_url;
        }
      });
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
