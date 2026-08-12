(function () {
  "use strict";

  var visitorKey = "lblx_visitor_id";
  var sessionKey = "lblx_session_id";

  function uid(prefix) {
    return prefix + "_" + Math.random().toString(36).slice(2) + Date.now().toString(36);
  }

  function stored(key, prefix) {
    try {
      var value = localStorage.getItem(key);
      if (!value) {
        value = uid(prefix);
        localStorage.setItem(key, value);
      }
      return value;
    } catch (_) {
      return uid(prefix);
    }
  }

  function pageSlug() {
    var meta = document.querySelector('meta[name="lbx-page-slug"]');
    if (meta && meta.content) return meta.content;
    var match = location.pathname.match(/\/go\/([^/]+)/);
    return match ? decodeURIComponent(match[1]) : "";
  }

  async function context() {
    if (window.LearnBluxorRuntime.context) return window.LearnBluxorRuntime.context;
    var embedded = document.getElementById("lbx-context");
    if (embedded && embedded.textContent) {
      try {
        window.LearnBluxorRuntime.context = JSON.parse(embedded.textContent);
        return window.LearnBluxorRuntime.context;
      } catch (_) {}
    }
    var response = await fetch("/api/v1/landing-pages/" + encodeURIComponent(pageSlug()) + "/context", {
      headers: { Accept: "application/json" },
      credentials: "same-origin"
    });
    window.LearnBluxorRuntime.context = (await response.json()).data;
    return window.LearnBluxorRuntime.context;
  }

  function money(amountMinor, currency) {
    return (currency || "BDT") + " " + Math.round((amountMinor || 0) / 100).toLocaleString("en-BD");
  }

  function setText(selector, value) {
    document.querySelectorAll(selector).forEach(function (node) {
      node.textContent = value == null ? "" : String(value);
    });
  }

  function setAttr(selector, attr, value) {
    document.querySelectorAll(selector).forEach(function (node) {
      if (value) node.setAttribute(attr, value);
    });
  }

  async function track(eventName, properties) {
    var data = await context();
    if (!data || !data.analytics || data.page.preview) return;
    return fetch("/api/v1/analytics/events", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        event_name: eventName,
        landing_page_id: data.analytics.landing_page_id,
        landing_page_version_id: data.analytics.landing_page_version_id,
        visitor_id: stored(visitorKey, "v"),
        session_id: stored(sessionKey, "s"),
        properties: properties || {}
      })
    }).catch(function () {});
  }

  function hydrateProduct(data) {
    var product = data.product || {};
    setText("[data-lbx-product-name]", product.name);
    setText("[data-lbx-product-description]", product.description);
    setText("[data-lbx-product-short-description]", product.short_description);
    setText("[data-lbx-product-price]", money(product.price_minor, product.currency));
    setText("[data-lbx-product-sale-price]", product.sale_price_minor ? money(product.sale_price_minor, product.currency) : "");
    setText("[data-lbx-product-category]", product.category);
    setAttr("[data-lbx-product-cover]", "src", product.cover);
  }

  function hydrateOffers(data) {
    Object.keys(data.offers || {}).forEach(function (key) {
      var offer = data.offers[key];
      setText('[data-lbx-offer-name="' + key + '"]', offer.name);
      setText('[data-lbx-offer-price="' + key + '"]', money(offer.price_minor, offer.currency));
      setText('[data-lbx-offer-regular-price="' + key + '"]', money(offer.regular_price_minor, offer.currency));
      setText('[data-lbx-offer-saving="' + key + '"]', money(offer.saving_minor, offer.currency));
    });
  }

  function wireCheckout(data) {
    document.querySelectorAll("[data-lbx-checkout]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        var key = button.getAttribute("data-lbx-checkout") || "single";
        track("checkout_started", { offer_key: key });
        location.href = "/checkout?lp=" + encodeURIComponent(data.page.slug) + "&offer=" + encodeURIComponent(key);
      });
    });
  }

  function wireInteractions() {
    document.querySelectorAll("[data-lbx-accordion-trigger]").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var id = trigger.getAttribute("data-lbx-accordion-trigger");
        document.querySelectorAll('[data-lbx-accordion-panel="' + id + '"]').forEach(function (panel) {
          panel.hidden = !panel.hidden;
        });
      });
    });

    document.querySelectorAll("[data-lbx-modal-open]").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var id = trigger.getAttribute("data-lbx-modal-open");
        document.querySelectorAll('[data-lbx-modal="' + id + '"]').forEach(function (modal) {
          modal.removeAttribute("hidden");
          modal.setAttribute("aria-hidden", "false");
        });
      });
    });

    document.querySelectorAll("[data-lbx-modal-close]").forEach(function (trigger) {
      trigger.addEventListener("click", function () {
        var id = trigger.getAttribute("data-lbx-modal-close");
        document.querySelectorAll('[data-lbx-modal="' + id + '"]').forEach(function (modal) {
          modal.setAttribute("hidden", "");
          modal.setAttribute("aria-hidden", "true");
        });
      });
    });

    document.querySelectorAll("[data-lbx-tab]").forEach(function (tab) {
      tab.addEventListener("click", function () {
        var id = tab.getAttribute("data-lbx-tab");
        document.querySelectorAll("[data-lbx-tab-panel]").forEach(function (panel) {
          panel.hidden = panel.getAttribute("data-lbx-tab-panel") !== id;
        });
      });
    });

    document.querySelectorAll("[data-lbx-track]").forEach(function (node) {
      node.addEventListener("click", function () {
        var eventName = node.getAttribute("data-lbx-track") || "custom_event";
        if (/^[a-z0-9_:-]{1,80}$/i.test(eventName)) {
          track(eventName === "cta_click" ? "cta_click" : "custom_event", { name: eventName });
        }
      });
    });
  }

  window.LearnBluxorRuntime = {
    context: null,
    getContext: context,
    track: track,
    formatMoney: money
  };

  document.addEventListener("DOMContentLoaded", function () {
    context().then(function (data) {
      hydrateProduct(data);
      hydrateOffers(data);
      wireCheckout(data);
      wireInteractions();
      track("landing_page_view");
    }).catch(function () {
      wireInteractions();
    });
  });
})();
