(function () {
  "use strict";

  var visitorKey = "lblx_visitor_id";
  var sessionKey = "lblx_session_id";
  var metaConfigPromise = null;
  var metaInitialized = false;
  var metaTracked = {};

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

  async function metaConfig() {
    if (metaConfigPromise) return metaConfigPromise;
    metaConfigPromise = fetch("/api/v1/tracking/config", {
      headers: { Accept: "application/json" },
      credentials: "same-origin"
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      return payload.data && payload.data.meta ? payload.data.meta : { enabled: false, pixel_id: "" };
    }).catch(function () {
      return { enabled: false, pixel_id: "" };
    });
    return metaConfigPromise;
  }

  function consentAllows(config) {
    if (!config || !config.enabled || !config.pixel_id) return false;
    if (!config.require_marketing_consent) return true;
    try {
      return localStorage.getItem("lbx_marketing_consent") === "true";
    } catch (_) {
      return false;
    }
  }

  function installFbq() {
    if (window.fbq) return;
    var fbq = function () {
      if (fbq.callMethod) fbq.callMethod.apply(fbq, arguments);
      else fbq.queue.push(arguments);
    };
    fbq.queue = [];
    fbq.loaded = true;
    fbq.version = "2.0";
    window.fbq = window._fbq = fbq;
  }

  async function initMeta() {
    var config = await metaConfig();
    if (!consentAllows(config)) return false;
    installFbq();
    if (!document.querySelector('script[data-lbx-meta-pixel="true"]')) {
      var script = document.createElement("script");
      script.async = true;
      script.defer = true;
      script.src = "https://connect.facebook.net/en_US/fbevents.js";
      script.setAttribute("data-lbx-meta-pixel", "true");
      document.head.appendChild(script);
    }
    if (!metaInitialized) {
      window.fbq("init", config.pixel_id);
      metaInitialized = true;
    }
    return true;
  }

  async function trackMeta(name, payload, options, key) {
    if (key && metaTracked[key]) return;
    if (!(await initMeta())) return;
    if (key) metaTracked[key] = true;
    try {
      if (options) window.fbq("track", name, payload || {}, options);
      else window.fbq("track", name, payload || {});
    } catch (_) {}
  }

  function primaryMetaItem(data) {
    if (data.product && data.product.content_id) {
      return {
        content_id: data.product.content_id,
        name: data.product.name,
        value: Math.round((data.product.price_minor || 0) / 100),
        currency: data.product.currency
      };
    }
    var offers = Object.keys(data.offers || {}).map(function (key) { return data.offers[key]; });
    var offer = offers.find(function (item) { return item.is_primary; }) || offers[0];
    return offer ? {
      content_id: offer.content_id || (offer.type + ":" + offer.backend_id),
      name: offer.name,
      value: Math.round((offer.price_minor || 0) / 100),
      currency: offer.currency
    } : null;
  }

  function trackMetaLandingView(data) {
    if (!data || !data.page || data.page.preview) return;
    var item = primaryMetaItem(data);
    trackMeta("PageView", {}, null, "PageView:" + location.pathname);
    if (item) {
      trackMeta("ViewContent", {
        content_ids: [item.content_id],
        content_name: item.name,
        content_type: "product",
        value: item.value,
        currency: item.currency
      }, null, "ViewContent:" + item.content_id);
    }
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
      trackMetaLandingView(data);
    }).catch(function () {
      wireInteractions();
    });
  });
})();
