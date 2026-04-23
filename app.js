(() => {
  "use strict";

  const DATA_URL = "/api/pics.php";
  const DATA_URL_FALLBACK = "data/pics.json";
  const BOOKS_URL = "data/books.json";
  const TAGS_URL = "data/tags.json";
  const PROD_ORIGIN_FALLBACK = "https://wordpics.amana.top";

  const SIZE_LABEL = {
    postcard: "葉書",
    businesscard: "名刺",
    square: "スクエア",
  };
  const ORIENTATION_LABEL = {
    landscape: "横",
    portrait: "縦",
    square: "正方形",
  };
  const SOURCE_LABEL = {
    official: "公式",
    user: "ユーザー生成",
  };

  /** @type {{pics: any[], books: any[], bookMap: Record<string, any>, tagCategories: any[], me: any | null}} */
  const store = { pics: [], books: [], bookMap: {}, tagCategories: [], me: null };

  /** Current filter state */
  const state = {
    q: "",
    size: "all",
    orientation: "all",
    source: "all",
    tag: "all",
    book: "all",
  };

  let currentPic = null;

  // Mobile breakpoint matches the CSS one used for the bottom-sheet filter
  const mqMobile = window.matchMedia("(max-width: 720px)");

  // ========== Utilities ==========
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const debounce = (fn, wait = 150) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  };

  const esc = (s) =>
    String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const isLocalDevHost = ["localhost", "127.0.0.1", "::1"].includes(location.hostname);

  function normalizeAssetPath(path) {
    if (!path) return "";
    if (/^https?:\/\//i.test(path)) return path;
    return path.startsWith("/") ? path : `/${path}`;
  }

  function isUploadPath(path) {
    return /^\/?uploads\//.test(path || "");
  }

  function buildAssetUrls(path) {
    const localUrl = normalizeAssetPath(path);
    if (!isLocalDevHost || !isUploadPath(localUrl)) {
      return { localUrl, remoteUrl: null };
    }
    return { localUrl, remoteUrl: `${PROD_ORIGIN_FALLBACK}${localUrl}` };
  }

  function setImageWithFallback(img, path) {
    const { localUrl, remoteUrl } = buildAssetUrls(path);
    if (!localUrl) return;

    if (remoteUrl) {
      const onError = () => {
        img.removeEventListener("error", onError);
        img.src = remoteUrl;
      };
      // Register before setting src so immediate 404s are also caught.
      img.addEventListener("error", onError);
    }
    img.src = localUrl;
  }

  /**
   * JSON を取得するヘルパー。404/非JSON などは失敗として投げる。
   */
  async function fetchJson(url) {
    const res = await fetch(url, { cache: "no-cache" });
    if (!res.ok) throw new Error(`${url} → HTTP ${res.status}`);
    const ct = res.headers.get("content-type") || "";
    const text = await res.text();
    // content-type が json でなくても、中身が JSON として parse できるなら許容
    try {
      return JSON.parse(text);
    } catch (e) {
      throw new Error(`${url} は JSON ではありません (content-type: ${ct})`);
    }
  }

  /**
   * 公開画像データを読み込む。
   * 基本は /api/pics.php を使い、data/pics.json は不足分を補う。
   * ローカル開発や PHP が動かない環境では data/pics.json にフォールバックする。
   */
  async function loadPics() {
    const dedupeById = (items) => {
      const seen = new Set();
      const out = [];
      items.forEach((item) => {
        const key = String(item?.id || item?.file || "");
        if (!key || seen.has(key)) return;
        seen.add(key);
        out.push(item);
      });
      return out;
    };

    try {
      const apiPics = await fetchJson(DATA_URL);
      try {
        const localPics = await fetchJson(DATA_URL_FALLBACK);
        // API を優先しつつ、JSON 側にしかない画像（例: 追加した公式画像）を補完する
        return dedupeById([...(apiPics || []), ...(localPics || [])]);
      } catch {
        return apiPics;
      }
    } catch (err) {
      console.warn(
        `[WordPics] API (${DATA_URL}) が利用できないため、${DATA_URL_FALLBACK} にフォールバックします。`,
        err
      );
      return await fetchJson(DATA_URL_FALLBACK);
    }
  }

  // ========== Init ==========
  async function init() {
    $("#year").textContent = new Date().getFullYear();

    const errors = [];

    // 画像データは必須。失敗するとページが成立しないのでエラー表示して return。
    try {
      store.pics = await loadPics();
    } catch (err) {
      console.error("[WordPics] 画像データの読み込み失敗:", err);
      $("#grid").innerHTML =
        `<p style="color: var(--text-muted)">データを読み込めませんでした: ${esc(err.message)}<br>` +
        `<small>DevTools の Console / Network タブで詳細を確認してください。</small></p>`;
      return;
    }

    // books.json / tags.json は失われても致命ではない（絞り込みが減るだけ）。
    try {
      store.books = await fetchJson(BOOKS_URL);
    } catch (err) {
      console.warn("[WordPics] books.json 読み込み失敗（書名表示が簡略化されます）:", err);
      errors.push(err.message);
      store.books = [];
    }
    try {
      const tagsJson = await fetchJson(TAGS_URL);
      store.tagCategories = Array.isArray(tagsJson?.categories) ? tagsJson.categories : [];
    } catch (err) {
      console.warn("[WordPics] tags.json 読み込み失敗（タグ分類が無効になります）:", err);
      errors.push(err.message);
      store.tagCategories = [];
    }
    store.bookMap = Object.fromEntries(store.books.map((b) => [b.abbr, b]));

    // 認証情報（me）はオプション。ローカル開発時は失敗してもスキップ。
    try {
      const meRes = await fetch("/api/auth/me.php", {
        cache: "no-cache",
        credentials: "same-origin",
      });
      if (meRes.ok) {
        const meJson = await meRes.json();
        store.me = meJson.user || null;
      }
    } catch {
      /* ignore */
    }

    if (errors.length) {
      console.info(
        "[WordPics] 一部のデータ読み込みに失敗しましたが、ギャラリー表示は続行します。"
      );
    }

    // ナビのログイン関連リンクを出し分け
    if (store.me) {
      $("#navMe").hidden = false;
      if (store.me.is_admin) $("#navAdmin").hidden = false;
    }

    buildTagSections();
    buildBookSelect();
    bindEvents();

    renderHeroStats();
    render();

    // URL に ?id=xxx があれば該当のモーダルを自動で開く
    const initialId = new URLSearchParams(location.search).get("id");
    if (initialId) {
      openModal(initialId);
    }

    // ブラウザバック／フォワードでモーダル状態を同期
    window.addEventListener("popstate", () => {
      const id = new URLSearchParams(location.search).get("id");
      const modal = $("#modal");
      if (id) {
        if (!currentPic || currentPic.id !== id) openModal(id, { updateUrl: false });
      } else if (modal && !modal.hidden) {
        closeModal({ updateUrl: false });
      }
    });
  }

  function buildTagSections() {
    // Count tag frequency from the actual dataset (hide tags with 0 uses)
    const freq = new Map();
    store.pics.forEach((p) => {
      (p.tags || []).forEach((t) => freq.set(t, (freq.get(t) || 0) + 1));
    });

    // Collect classified tags from tags.json; everything else → "その他"
    const classified = new Set();
    const sections = store.tagCategories
      .map((cat) => {
        const tags = (cat.tags || []).filter((t) => freq.has(t));
        tags.forEach((t) => classified.add(t));
        return { key: cat.key, label: cat.label, tags };
      })
      .filter((s) => s.tags.length > 0);

    const others = Array.from(freq.keys())
      .filter((t) => !classified.has(t))
      .sort((a, b) => a.localeCompare(b, "ja"));
    if (others.length) {
      sections.push({ key: "other", label: "その他", tags: others });
    }

    const host = $("#tagSections");
    host.innerHTML = sections
      .map(
        (s) => `
          <div class="tag-section" data-cat="${esc(s.key)}">
            <div class="tag-section-label">${esc(s.label)}</div>
            <div class="chips chips--sub" data-filter="tag" role="group" aria-label="${esc(s.label)}">
              ${s.tags
                .map(
                  (t) =>
                    `<button type="button" class="chip" data-value="${esc(t)}">${esc(t)}</button>`
                )
                .join("")}
            </div>
          </div>`
      )
      .join("");
  }

  function buildBookSelect() {
    const select = $("#bookSelect");
    const usedAbbrs = new Set(store.pics.map((p) => p.book));
    const used = store.books.filter((b) => usedAbbrs.has(b.abbr));
    used.sort((a, b) => a.no - b.no);
    used.forEach((b) => {
      const opt = document.createElement("option");
      opt.value = b.abbr;
      opt.textContent = `${b.jaShort}（${b.en}）`;
      select.appendChild(opt);
    });
  }

  function bindEvents() {
    // Search
    $("#searchInput").addEventListener(
      "input",
      debounce((e) => {
        state.q = e.target.value.trim().toLowerCase();
        render();
      }, 120)
    );

    // Header filter toggle (shared by desktop + mobile)
    $("#filterToggle").addEventListener("click", toggleFilterPanel);

    // Close buttons (backdrop + X + "結果を見る" on mobile)
    document.addEventListener("click", (e) => {
      const target = e.target instanceof Element ? e.target.closest("[data-close-filter='1']") : null;
      if (target) {
        closeFilterPanel();
      }
    });
    $("#applyFilters").addEventListener("click", closeFilterPanel);

    // Delegate chip clicks (works across all tag sections + size/orientation)
    // Each "chips" group has its own scope so inactive siblings in same group clear.
    // For tag sections we treat all chips with data-filter="tag" as one logical group.
    document.addEventListener("click", (e) => {
      const chip = e.target.closest(".chip");
      if (!chip) return;
      const group = chip.closest(".chips");
      if (!group) return;
      const key = group.dataset.filter;
      if (!key) return;

      if (key === "tag") {
        // Clear active state across all tag chip groups
        $$('.chips[data-filter="tag"] .chip').forEach((c) => c.classList.remove("is-active"));
      } else {
        group.querySelectorAll(".chip").forEach((c) => c.classList.remove("is-active"));
      }
      chip.classList.add("is-active");
      state[key] = chip.dataset.value;
      render();
    });

    // Book select
    $("#bookSelect").addEventListener("change", (e) => {
      state.book = e.target.value;
      render();
    });

    // Clear filters
    $("#clearFilters").addEventListener("click", clearFilters);
    $("#emptyReset").addEventListener("click", () => {
      clearFilters();
      closeFilterPanel();
    });

    // Modal close (click outside / ESC)
    $("#modal").addEventListener("click", (e) => {
      if (e.target.dataset.close === "1") closeModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      if (!$("#modal").hidden) closeModal();
      else if (document.body.classList.contains("is-filter-open")) closeFilterPanel();
    });

    // When switching between mobile ↔ desktop, clean up any modal-ish state
    mqMobile.addEventListener?.("change", () => {
      // Closing on breakpoint crossover avoids half-open bottom sheets on desktop
      closeFilterPanel();
    });

    // Report form
    $("#modalReport").addEventListener("click", openReportForm);
    $("#reportCancel").addEventListener("click", resetReportForm);
    $("#reportSubmit").addEventListener("click", submitReport);
  }

  // ========== Filter panel (inline on desktop, bottom sheet on mobile) ==========
  function toggleFilterPanel() {
    if (document.body.classList.contains("is-filter-open")) {
      closeFilterPanel();
    } else {
      openFilterPanel();
    }
  }

  function openFilterPanel() {
    const panel = $("#filterPanel");
    const backdrop = $("#filterBackdrop");
    panel.hidden = false;
    backdrop.hidden = false;
    // rAF ensures the transition triggers after `hidden` is removed
    requestAnimationFrame(() => {
      document.body.classList.add("is-filter-open");
    });
    $("#filterToggle").setAttribute("aria-expanded", "true");
    if (mqMobile.matches) {
      document.body.style.overflow = "hidden";
    }
  }

  function closeFilterPanel() {
    const panel = $("#filterPanel");
    const backdrop = $("#filterBackdrop");
    document.body.classList.remove("is-filter-open");
    document.body.style.overflow = "";
    $("#filterToggle").setAttribute("aria-expanded", "false");
    // Wait for the CSS transition to finish before unmounting
    const delay = mqMobile.matches ? 280 : 220;
    setTimeout(() => {
      if (!document.body.classList.contains("is-filter-open")) {
        panel.hidden = true;
        backdrop.hidden = true;
      }
    }, delay);
  }

  function clearFilters() {
    state.q = "";
    state.size = "all";
    state.orientation = "all";
    state.source = "all";
    state.tag = "all";
    state.book = "all";
    $("#searchInput").value = "";
    $("#bookSelect").value = "all";
    // Reset chips: for tag group, keep only the "all" chip active
    $$(".chips").forEach((group) => {
      const key = group.dataset.filter;
      group.querySelectorAll(".chip").forEach((c) => {
        if (key === "tag") {
          c.classList.toggle("is-active", c.dataset.value === "all" && group.classList.contains("chips--all"));
        } else {
          c.classList.toggle("is-active", c.dataset.value === "all");
        }
      });
    });
    render();
  }

  // ========== Filtering ==========
  function filterPics() {
    const q = state.q;
    return store.pics.filter((p) => {
      if (state.size !== "all" && p.size !== state.size) return false;
      if (state.orientation !== "all" && p.orientation !== state.orientation) return false;
      if (state.source !== "all" && (p.source || "official") !== state.source) return false;
      if (state.tag !== "all" && !(p.tags || []).includes(state.tag)) return false;
      if (state.book !== "all" && p.book !== state.book) return false;

      if (q) {
        const book = store.bookMap[p.book] || {};
        const hay = [
          p.id,
          p.verseText,
          p.citationJa,
          (p.tags || []).join(" "),
          book.ja,
          book.jaShort,
          book.en,
          book.abbr,
        ]
          .filter(Boolean)
          .join(" ")
          .toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  }

  // ========== Rendering ==========
  function render() {
    const items = filterPics();
    const grid = $("#grid");
    const empty = $("#emptyState");

    $("#resultCount").textContent = `${items.length} 件 / 全 ${store.pics.length} 件`;
    updateFilterCount(items.length);

    if (items.length === 0) {
      grid.innerHTML = "";
      empty.hidden = false;
    } else {
      empty.hidden = true;
      grid.innerHTML = items.map(cardHTML).join("");
      grid.querySelectorAll("img[data-image-path]").forEach((img) => {
        setImageWithFallback(img, img.dataset.imagePath);
      });
      grid.querySelectorAll(".card").forEach((el) => {
        el.addEventListener("click", () => openModal(el.dataset.id));
        el.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            openModal(el.dataset.id);
          }
        });
      });
    }

    // Also update the "結果を見る" CTA in the mobile bottom sheet
    const apply = $("#applyFilters");
    if (apply) apply.textContent = `${items.length} 件を見る`;
  }

  function cardHTML(p) {
    const book = store.bookMap[p.book];
    const bookLabel = book ? book.jaShort : p.book;
    const orientationClass =
      p.orientation === "portrait" ? "is-portrait" : p.orientation === "square" ? "is-square" : "";
    const badgeList = [
      `<span class="badge">${esc(SIZE_LABEL[p.size] || p.size)}</span>`,
      `<span class="badge is-neutral">${esc(bookLabel)}</span>`,
    ];
    if (p.source === "user") {
      badgeList.unshift(`<span class="badge badge--user">ユーザー生成</span>`);
    }
    const badges = badgeList.join("");
    return `
      <article class="card" role="listitem" tabindex="0" data-id="${esc(p.id)}"
               aria-label="${esc(p.citationJa)}">
        <div class="card-thumb ${orientationClass}">
          <img
            data-image-path="${esc(p.file)}"
            src="${esc(normalizeAssetPath(p.file))}"
            alt="${esc(p.verseText)}"
            loading="lazy"
            decoding="async"
          />
        </div>
        <div class="card-body">
          <div class="badges card-badges">${badges}</div>
          <div class="card-verse">${esc(p.verseText)}</div>
          <div class="card-citation">${esc(p.citationJa)}</div>
        </div>
      </article>
    `;
  }

  function updateFilterCount(resultCount) {
    const count = typeof resultCount === "number" ? resultCount : filterPics().length;
    const el = $("#filterCount");
    if (el) {
      el.hidden = false;
      el.textContent = String(count);
    }
  }

  function renderHeroStats() {
    const el = $("#heroStats");
    const n = store.pics.length;
    const sizes = new Set(store.pics.map((p) => p.size));
    const books = new Set(store.pics.map((p) => p.book));
    el.innerHTML = [
      `<span class="pill">${n} 枚の御言画像</span>`,
      `<span class="pill">${sizes.size} サイズ対応</span>`,
      `<span class="pill">${books.size} 書からの御言</span>`,
    ].join("");
  }

  // ========== Modal ==========
  function openModal(id, opts = {}) {
    const p = store.pics.find((x) => x.id === id);
    if (!p) return;
    currentPic = p;
    const book = store.bookMap[p.book];

    const modalImg = $("#modalImg");
    setImageWithFallback(modalImg, p.file);
    modalImg.alt = p.verseText;
    $("#modalVerse").textContent = p.verseText;
    $("#modalCitation").textContent = p.citationJa;
    $("#modalId").textContent = p.id;
    $("#modalSize").textContent = SIZE_LABEL[p.size] || p.size;
    $("#modalOrientation").textContent = ORIENTATION_LABEL[p.orientation] || p.orientation;
    $("#modalBook").textContent = book ? `${book.ja}（${book.en}）` : p.book;

    resetReportForm();

    const tagsHtml = (p.tags || [])
      .map(
        (t) =>
          `<button type="button" class="tag tag-link" data-tag="${esc(t)}">${esc(t)}</button>`
      )
      .join("");
    $("#modalTags").innerHTML = tagsHtml
      ? `<span class="tag-list">${tagsHtml}</span>`
      : "—";

    const modalBadges = [
      `<span class="badge">${esc(SIZE_LABEL[p.size] || p.size)}</span>`,
      `<span class="badge is-neutral">${esc(ORIENTATION_LABEL[p.orientation] || p.orientation)}</span>`,
    ];
    if (p.source === "user") {
      modalBadges.unshift(`<span class="badge badge--user">ユーザー生成</span>`);
    }
    $("#modalBadges").innerHTML = modalBadges.join("");

    const dl = $("#modalDownload");
    const { localUrl: fileUrl, remoteUrl: fallbackFileUrl } = buildAssetUrls(p.file);
    const modalFileUrl = fallbackFileUrl || fileUrl;
    dl.href = modalFileUrl;
    const ext = (p.file.split(".").pop() || "png").split("?")[0];
    dl.setAttribute("download", `${p.id}-${p.book}-${p.chapter}-${p.verse}.${ext}`);
    $("#modalOpen").href = modalFileUrl;
    const shareUrl = `${location.pathname}?id=${encodeURIComponent(p.id)}`;
    $("#modalShare").href = shareUrl;

    $("#modalTags")
      .querySelectorAll(".tag-link")
      .forEach((btn) => {
        btn.title = `「${btn.dataset.tag}」で絞り込む`;
        btn.setAttribute("aria-label", `タグ「${btn.dataset.tag}」で絞り込む`);
        btn.addEventListener("click", () => {
          closeModal();
          applyTagFilter(btn.dataset.tag);
        });
      });

    const modal = $("#modal");
    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    // URL を ?id=xxx に同期（共有しやすくする／ブラウザバックで閉じる）
    if (opts.updateUrl !== false) {
      const current = new URLSearchParams(location.search).get("id");
      if (current !== p.id) {
        history.pushState({ modalId: p.id }, "", shareUrl);
      }
    }
  }

  function applyTagFilter(tag) {
    state.tag = tag;
    $$('.chips[data-filter="tag"] .chip').forEach((c) => {
      c.classList.toggle("is-active", c.dataset.value === tag);
    });
    render();
    document.getElementById("gallery").scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function closeModal(opts = {}) {
    const modal = $("#modal");
    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    resetReportForm();
    currentPic = null;
    // Restore body scroll only if no other modal/panel needs it locked
    if (!document.body.classList.contains("is-filter-open") || !mqMobile.matches) {
      document.body.style.overflow = "";
    }

    // URL から ?id を取り除く
    if (opts.updateUrl !== false) {
      const params = new URLSearchParams(location.search);
      if (params.has("id")) {
        params.delete("id");
        const qs = params.toString();
        history.pushState(null, "", location.pathname + (qs ? `?${qs}` : ""));
      }
    }
  }

  // ========== 誤字報告 ==========
  function resetReportForm() {
    const form = $("#reportForm");
    const status = $("#reportStatus");
    const textarea = $("#reportMessage");
    if (!form) return;
    form.hidden = true;
    if (textarea) textarea.value = "";
    if (status) {
      status.textContent = "";
      status.className = "report-status";
    }
  }

  function openReportForm() {
    if (!currentPic) return;
    $("#reportForm").hidden = false;
    $("#reportMessage").focus();
  }

  async function submitReport() {
    if (!currentPic) return;
    const message = $("#reportMessage").value.trim();
    const status = $("#reportStatus");
    if (!message) {
      status.textContent = "報告内容を入力してください。";
      status.className = "report-status is-error";
      return;
    }
    const btn = $("#reportSubmit");
    btn.disabled = true;
    status.textContent = "送信中…";
    status.className = "report-status";
    try {
      const res = await fetch("/api/report.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ submission_id: currentPic.id, message }),
        credentials: "same-origin",
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || "送信に失敗しました。");
      status.textContent = "ご報告ありがとうございました。管理者に通知しました。";
      status.className = "report-status is-success";
      $("#reportMessage").value = "";
    } catch (err) {
      status.textContent = err.message || "送信に失敗しました。";
      status.className = "report-status is-error";
    } finally {
      btn.disabled = false;
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
