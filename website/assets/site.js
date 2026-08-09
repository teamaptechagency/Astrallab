// astralab.com — the only script on the site.
//
// Two jobs: the mobile menu, and filling the product grid from the hub's
// public catalogue API so version numbers and availability are never stale
// copy that someone forgot to update after a release.

const HUB = window.ASTRALAB_HUB || "http://localhost:3200";

/* ---- mobile menu ---- */
const toggle = document.querySelector(".nav-toggle");
const nav = document.getElementById("site-nav");

if (toggle && nav) {
  // Hidden is applied here rather than in the HTML so the menu stays visible
  // for anyone whose JS never runs — a broken script must not cost navigation.
  const isMobile = () => window.matchMedia("(max-width: 820px)").matches;
  if (isMobile()) nav.hidden = true;

  toggle.addEventListener("click", () => {
    nav.hidden = !nav.hidden;
    toggle.setAttribute("aria-expanded", String(!nav.hidden));
  });

  window.addEventListener("resize", () => {
    nav.hidden = isMobile() ? nav.hidden : false;
  });
}

/* ---- product catalogue ---- */
const grid = document.getElementById("product-grid");

function escapeHtml(value) {
  return String(value).replace(
    /[&<>"']/g,
    (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
  );
}

function productCard(product) {
  const available = product.available;
  const size = product.downloadSizeBytes
    ? ` · ${(product.downloadSizeBytes / 1048576).toFixed(1)} MB`
    : "";

  return `
    <article class="product">
      <div class="product-top">
        <h3>${escapeHtml(product.name)}</h3>
        <span class="product-status${available ? "" : " product-status--soon"}">
          ${available ? "Available" : "Coming soon"}
        </span>
      </div>
      <p style="color:var(--ink-2);font-size:.9375rem">${escapeHtml(product.summary || "")}</p>
      <p class="product-version">
        ${available ? `v${escapeHtml(product.latestVersion)}${size}` : "Not released yet"}
      </p>
      ${
        available
          ? `<a class="btn btn--primary" href="/shop/">Buy now</a>`
          : `<span class="btn btn--ghost" aria-disabled="true">Not yet on sale</span>`
      }
    </article>`;
}

async function loadCatalogue() {
  if (!grid) return;

  try {
    const res = await fetch(`${HUB}/api/public/products`, { mode: "cors" });
    if (!res.ok) throw new Error(`hub responded ${res.status}`);

    const { products } = await res.json();
    if (!Array.isArray(products) || products.length === 0) {
      grid.innerHTML = `<p style="color:var(--ink-3)">Nothing on sale just yet — check back shortly.</p>`;
      return;
    }

    grid.innerHTML = products.map(productCard).join("");
  } catch (err) {
    // Never leave shimmering placeholders behind. A visitor seeing a permanent
    // loading state assumes the whole site is broken; an honest line plus a
    // working contact route does not lose the sale.
    console.error("[astralab] catalogue unavailable:", err);
    grid.innerHTML = `
      <p style="color:var(--ink-3)">
        Our catalogue service is briefly unavailable.
        <a href="/contact/" style="color:var(--brand)">Contact us</a> and we will send the details directly.
      </p>`;
  }
}

loadCatalogue();

/* ---- care plan billing period ---- */
//
// Prices for all three periods live in data attributes on each plan, rather
// than being derived from the monthly figure. A percentage applied in JS would
// produce numbers like ৳4,320 that nobody would actually print on an invoice;
// the discounted prices are decided once and stated plainly.

const billing = document.querySelector(".billing");
const plans = document.querySelectorAll(".plan");

if (billing && plans.length) {
  const LABEL = { monthly: "/month", six: " for 6 months", year: " for a year" };
  const MONTHS = { monthly: 1, six: 6, year: 12 };
  const taka = (n) => `৳${n.toLocaleString("en-US")}`;

  function render(period) {
    for (const plan of plans) {
      const total = Number(plan.dataset[period] || 0);
      plan.querySelector(".plan-amount").textContent = taka(total);
      plan.querySelector(".plan-period").textContent = LABEL[period];

      // On the longer terms, show what it works out to per month — that is the
      // number a shop owner compares against, and hiding it makes the bigger
      // total look worse than it is.
      const equiv = plan.querySelector(".plan-equiv");
      if (period === "monthly") {
        equiv.textContent = "";
      } else {
        const perMonth = Math.round(total / MONTHS[period]);
        const monthly = Number(plan.dataset.monthly || 0);
        const saved = monthly * MONTHS[period] - total;
        equiv.textContent = `${taka(perMonth)}/month · you save ${taka(saved)}`;
      }
    }
  }

  billing.addEventListener("click", (event) => {
    const button = event.target.closest(".billing-opt");
    if (!button) return;

    for (const opt of billing.querySelectorAll(".billing-opt")) {
      const active = opt === button;
      opt.classList.toggle("is-active", active);
      opt.setAttribute("aria-pressed", String(active));
    }
    render(button.dataset.period);
  });

  render("monthly");
}
