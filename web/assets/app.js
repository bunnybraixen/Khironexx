(function () {
  "use strict";

  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
      return;
    }
    callback();
  }

  onReady(function () {
    var body = document.body;
    var mobileQuery = window.matchMedia("(max-width: 1100px)");
    var sidebar = document.getElementById("sidebar");
    var sidebarToggle = document.getElementById("sidebar-toggle");
    var mobileToggle = document.getElementById("sidebar-mobile-toggle");
    var sidebarOverlay = document.getElementById("sidebar-overlay");

    function protectBranding() {
      var protectedNodes = Array.prototype.slice.call(document.querySelectorAll("[data-brand-lock]"));
      if (!protectedNodes.length) {
        return;
      }

      function preventDefault(event) {
        event.preventDefault();
      }

      function lockNode(node, expectedSrc) {
        if (!node) {
          return;
        }
        node.classList.add("brand-locked");
        node.setAttribute("draggable", "false");
        if (expectedSrc && node.getAttribute("src") !== expectedSrc) {
          node.setAttribute("src", expectedSrc);
        }
        node.removeEventListener("dragstart", preventDefault);
        node.removeEventListener("contextmenu", preventDefault);
        node.addEventListener("dragstart", preventDefault);
        node.addEventListener("contextmenu", preventDefault);
      }

      var registry = protectedNodes.map(function (node) {
        var source = node.getAttribute("src");
        lockNode(node, source);
        return {
          key: node.getAttribute("data-brand-lock"),
          current: node,
          template: node.cloneNode(true),
          parent: node.parentNode,
          nextSibling: node.nextSibling,
          src: source
        };
      });

      function restoreNode(item) {
        if (!item.parent || !item.parent.isConnected) {
          return;
        }
        var restored = item.template.cloneNode(true);
        restored.setAttribute("data-brand-lock", item.key);
        lockNode(restored, item.src);
        if (item.nextSibling && item.nextSibling.parentNode === item.parent) {
          item.parent.insertBefore(restored, item.nextSibling);
        } else {
          item.parent.appendChild(restored);
        }
        item.current = restored;
      }

      function enforceBranding() {
        registry.forEach(function (item) {
          if (!item.current || !document.body.contains(item.current)) {
            restoreNode(item);
            return;
          }

          lockNode(item.current, item.src);

          var computedStyle = window.getComputedStyle(item.current);
          if (computedStyle.display === "none" || computedStyle.visibility === "hidden" || computedStyle.opacity === "0") {
            item.current.style.display = "";
            item.current.style.visibility = "";
            item.current.style.opacity = "";
          }
        });
      }

      var enforceQueued = false;
      function queueEnforce() {
        if (enforceQueued) {
          return;
        }
        enforceQueued = true;
        window.requestAnimationFrame(function () {
          enforceQueued = false;
          enforceBranding();
        });
      }

      enforceBranding();

      var observer = new MutationObserver(queueEnforce);
      observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["style", "class", "src"]
      });

      window.setInterval(enforceBranding, 2500);

      document.addEventListener("contextmenu", function (event) {
        if (event.target && event.target.closest && event.target.closest("[data-brand-lock]")) {
          event.preventDefault();
        }
      });
    }

    protectBranding();

    function initCookieNotice() {
      var cookieTrigger = document.querySelector("[data-cookie-open]");
      var cookieModal = document.querySelector("[data-cookie-modal]");
      if (!cookieTrigger || !cookieModal) {
        return;
      }

      var cookieDialog = cookieModal.querySelector(".cookie-modal-dialog");
      var cookieCloseTargets = cookieModal.querySelectorAll("[data-cookie-close]");

      function openCookieModal() {
        cookieModal.hidden = false;
        cookieModal.setAttribute("aria-hidden", "false");
        cookieTrigger.setAttribute("aria-expanded", "true");
        document.body.classList.add("cookie-modal-open");
        if (cookieDialog) {
          cookieDialog.focus();
        }
      }

      function closeCookieModal(focusTrigger) {
        cookieModal.hidden = true;
        cookieModal.setAttribute("aria-hidden", "true");
        cookieTrigger.setAttribute("aria-expanded", "false");
        document.body.classList.remove("cookie-modal-open");
        if (focusTrigger) {
          cookieTrigger.focus();
        }
      }

      cookieTrigger.addEventListener("click", function () {
        openCookieModal();
      });

      cookieCloseTargets.forEach(function (node) {
        node.addEventListener("click", function (event) {
          event.preventDefault();
          closeCookieModal(true);
        });
      });

      document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && cookieModal.hidden === false) {
          closeCookieModal(true);
        }
      });
    }

    initCookieNotice();

    function updateSidebarToggleExpanded() {
      if (!sidebarToggle) {
        return;
      }
      if (mobileQuery.matches) {
        sidebarToggle.setAttribute("aria-expanded", body.classList.contains("sidebar-open") ? "true" : "false");
        return;
      }
      sidebarToggle.setAttribute("aria-expanded", body.classList.contains("sidebar-collapsed") ? "false" : "true");
    }

    function setMobileSidebar(open) {
      var isOpen = Boolean(open);
      body.classList.toggle("sidebar-open", isOpen);
      if (mobileToggle) {
        mobileToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      }
      updateSidebarToggleExpanded();
    }

    function syncResponsiveSidebarState() {
      if (mobileQuery.matches) {
        body.classList.remove("sidebar-collapsed");
        setMobileSidebar(false);
        return;
      }
      setMobileSidebar(false);
    }

    function closeMobileSidebarIfNeeded() {
      if (mobileQuery.matches) {
        setMobileSidebar(false);
      }
    }

    if (mobileToggle) {
      mobileToggle.addEventListener("click", function () {
        setMobileSidebar(!body.classList.contains("sidebar-open"));
      });
    }

    if (sidebarOverlay) {
      sidebarOverlay.addEventListener("click", function () {
        setMobileSidebar(false);
      });
    }

    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", function () {
        if (mobileQuery.matches) {
          setMobileSidebar(!body.classList.contains("sidebar-open"));
          return;
        }
        body.classList.toggle("sidebar-collapsed");
        updateSidebarToggleExpanded();
      });
    }

    if (sidebar) {
      var sidebarLinks = sidebar.querySelectorAll(".sidebar-link");
      sidebarLinks.forEach(function (link) {
        link.addEventListener("click", closeMobileSidebarIfNeeded);
      });
    }

    syncResponsiveSidebarState();
    if (typeof mobileQuery.addEventListener === "function") {
      mobileQuery.addEventListener("change", syncResponsiveSidebarState);
    } else if (typeof mobileQuery.addListener === "function") {
      mobileQuery.addListener(syncResponsiveSidebarState);
    }
    window.addEventListener("resize", syncResponsiveSidebarState);

    var userDropdowns = Array.prototype.slice.call(document.querySelectorAll("[data-user-dropdown]"));
    if (!userDropdowns.length) {
      return;
    }

    var dropdownStates = userDropdowns.map(function (dropdown) {
      return {
        dropdown: dropdown,
        trigger: dropdown.querySelector("[data-user-trigger]"),
        menu: dropdown.querySelector("[data-user-menu]"),
        isOpen: false
      };
    });

    function closeAllDropdowns(exceptIndex, focusTrigger) {
      dropdownStates.forEach(function (state, index) {
        if (index === exceptIndex) {
          return;
        }
        state.isOpen = false;
        state.dropdown.classList.remove("is-open");
        if (state.trigger) {
          state.trigger.setAttribute("aria-expanded", "false");
        }
      });
    }

    dropdownStates.forEach(function (state, index) {
      if (!state.trigger || !state.menu) {
        return;
      }

      state.trigger.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        state.isOpen = !state.isOpen;
        state.dropdown.classList.toggle("is-open", state.isOpen);
        state.trigger.setAttribute("aria-expanded", state.isOpen ? "true" : "false");
        closeAllDropdowns(index, false);
      });

      state.menu.addEventListener("click", function (event) {
        event.stopPropagation();
      });
    });

    document.addEventListener("click", function (event) {
      dropdownStates.forEach(function (state) {
        if (!state.dropdown.contains(event.target)) {
          state.isOpen = false;
          state.dropdown.classList.remove("is-open");
          if (state.trigger) {
            state.trigger.setAttribute("aria-expanded", "false");
          }
        }
      });
    });

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Escape") {
        return;
      }
      dropdownStates.forEach(function (state) {
        state.isOpen = false;
        state.dropdown.classList.remove("is-open");
        if (state.trigger) {
          state.trigger.setAttribute("aria-expanded", "false");
        }
      });
      setMobileSidebar(false);
    });
  });
})();
