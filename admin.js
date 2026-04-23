(() => {
  "use strict";

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s ?? "").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");

  const state = {
    csrf: null,
    subStatus: "pending",
    repStatus: "open",
    subMap: new Map(),
  };

  const STATUS_LABEL = { pending: "承認待ち", approved: "公開中", rejected: "却下", unpublished: "非公開" };
  const FILTER_LABEL = { pending: "承認待ち", approved: "公開中", rejected: "却下", unpublished: "非公開", all: "すべて" };
  const EDIT_SIZE = ["postcard", "businesscard", "square"];
  const EDIT_ORIENTATION = ["landscape", "portrait", "square"];

  async function init() {
    $("#year").textContent = new Date().getFullYear();

    const meRes = await fetch("/api/auth/me.php", { credentials: "same-origin" });
    const me = await meRes.json().catch(() => ({}));
    if (!me.user || !me.user.is_admin) {
      $("#loginRequired").hidden = false;
      return;
    }
    state.csrf = me.csrf;
    $("#adminPanel").hidden = false;

    await syncOfficialFromJson();
    bindTabs();
    bindChipGroups();
    bindEditModal();
    await loadSubs();
    await loadReports(); // load counts only
  }

  async function syncOfficialFromJson() {
    try {
      const res = await fetch("/api/admin/sync_official_from_json.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ csrf: state.csrf }),
        credentials: "same-origin",
      });
      if (!res.ok) return;
      await res.json().catch(() => ({}));
    } catch {
      // 管理画面本体の表示は継続
    }
  }

  function bindTabs() {
    $$(".tab").forEach((t) =>
      t.addEventListener("click", () => {
        $$(".tab").forEach((x) => x.classList.remove("is-active"));
        t.classList.add("is-active");
        const target = t.dataset.tab;
        $("#tabSubmissions").hidden = target !== "submissions";
        $("#tabReports").hidden = target !== "reports";
        if (target === "reports") loadReports();
      })
    );
  }

  function bindChipGroups() {
    $$(".chips").forEach((group) => {
      group.addEventListener("click", (e) => {
        const chip = e.target.closest(".chip");
        if (!chip) return;
        group.querySelectorAll(".chip").forEach((c) => c.classList.remove("is-active"));
        chip.classList.add("is-active");
        const key = group.dataset.filter;
        if (key === "status") {
          state.subStatus = chip.dataset.value;
          loadSubs();
        } else if (key === "rstatus") {
          state.repStatus = chip.dataset.value;
          loadReports();
        }
      });
    });
  }

  // ========== Submissions ==========
  async function loadSubs() {
    const res = await fetch(`/api/admin/submissions.php?status=${encodeURIComponent(state.subStatus)}`, {
      credentials: "same-origin",
    });
    const data = await res.json().catch(() => ({}));
    $("#subCount").textContent = data.counts?.pending_submissions ?? 0;
    $("#repCount").textContent = data.counts?.open_reports ?? 0;
    updateStatusFilterCounts(data.counts?.submissions_by_status || {});
    const items = data.items || [];
    state.subMap = new Map(items.map((x) => [String(x.id), x]));
    const host = $("#subsList");
    $("#subsEmpty").hidden = items.length > 0;
    host.innerHTML = items.map(renderSubItem).join("");
    host.querySelectorAll("[data-sub-action]").forEach((btn) =>
      btn.addEventListener("click", () => handleSubAction(btn))
    );
  }

  function updateStatusFilterCounts(counts) {
    $$('.chips[data-filter="status"] .chip').forEach((chip) => {
      const key = chip.dataset.value;
      const base = FILTER_LABEL[key] || chip.textContent.replace(/\s*\(\d+\)\s*$/, "");
      const n = Number.isFinite(Number(counts[key])) ? Number(counts[key]) : 0;
      chip.textContent = `${base} (${n})`;
    });
  }

  function renderSubItem(s) {
    const statusLabel = STATUS_LABEL[s.status] || s.status;
    const tags = (s.tags || []).map((t) => `<span class="chip" style="pointer-events:none">${esc(t)}</span>`).join(" ");
    const author = s.author_email ? `${esc(s.author_email)}` : "(公式)";
    const revised = s.revised_file
      ? `<p class="sub">🎨 改訂版公開中 / <a href="${esc(s.original_file)}" target="_blank" rel="noopener">原本を見る</a></p>`
      : "";
    const rejectionReason = s.rejectionReason ? `<p class="sub" style="color:#b42318">却下理由: ${esc(s.rejectionReason)}</p>` : "";
    const notes = s.notes ? `<p class="sub">メモ: ${esc(s.notes)}</p>` : "";

    const primaryFile = s.revised_file || s.original_file;

    const actions = [];
    actions.push(`<button class="btn-ghost" data-sub-action="edit_props" data-id="${esc(s.id)}">プロパティ編集</button>`);
    if (s.status === "pending" || s.status === "rejected" || s.status === "unpublished") {
      actions.push(`<button class="btn-primary" data-sub-action="approve" data-id="${esc(s.id)}">承認して公開</button>`);
    }
    if (s.status !== "rejected") {
      actions.push(`<button class="btn-ghost" data-sub-action="reject" data-id="${esc(s.id)}">却下</button>`);
    }
    actions.push(`<button class="btn-ghost" data-sub-action="revision" data-id="${esc(s.id)}">改訂版を上げる</button>`);
    if (s.status === "approved") {
      actions.push(`<button class="btn-ghost btn-ghost--danger" data-sub-action="unpublish" data-id="${esc(s.id)}">非公開にする</button>`);
    }

    return `
      <article class="adminitem">
        <div class="thumb"><img src="${esc(primaryFile)}" alt="" loading="lazy"></div>
        <div class="meta">
          <div class="citation">${esc(s.citationJa)}</div>
          <p class="verse">${esc(s.verseText)}</p>
          <p class="sub">
            <span class="pill is-${s.status}">${esc(statusLabel)}</span>
            · ${esc(s.size)} / ${esc(s.orientation)}
            · 投稿者: ${author}
            · ${esc(s.createdAt)}
          </p>
          ${tags ? `<div class="chips" style="pointer-events:none">${tags}</div>` : ""}
          ${notes}
          ${revised}
          ${rejectionReason}
        </div>
        <div class="actions">${actions.join("")}</div>
      </article>`;
  }

  async function handleSubAction(btn) {
    const id = btn.dataset.id;
    const action = btn.dataset.subAction;
    if (!id || !action) return;

    if (action === "edit_props") {
      openEditModal(id);
      return;
    } else if (action === "reject") {
      const reason = window.prompt("却下理由（投稿者に通知されます）", "") || "";
      if (!window.confirm("却下します。よろしいですか？")) return;
      await postJson("/api/admin/submission_action.php", { id, action, reason });
    } else if (action === "revision") {
      await uploadRevision(id);
      return;
    } else {
      const label = action === "approve" ? "承認して公開" : action === "unpublish" ? "非公開" : action;
      if (!window.confirm(`${label} します。よろしいですか？`)) return;
      await postJson("/api/admin/submission_action.php", { id, action });
    }
    await loadSubs();
  }

  function bindEditModal() {
    const modal = $("#editModal");
    const form = $("#editForm");
    if (!modal || !form) return;

    modal.addEventListener("click", (e) => {
      const target = e.target instanceof Element ? e.target.closest("[data-edit-close='1']") : null;
      if (target) closeEditModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modal.hidden) closeEditModal();
    });
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      await saveEditModal();
    });
  }

  function openEditModal(id) {
    const s = state.subMap.get(String(id));
    if (!s) return;
    $("#editSubmissionId").value = String(s.id || "");
    $("#editBookAbbr").value = s.book || "";
    $("#editChapter").value = String(s.chapter || "");
    $("#editVerse").value = s.verse || "";
    $("#editCitationJa").value = s.citationJa || "";
    $("#editVerseText").value = s.verseText || "";
    $("#editSize").value = s.size || "postcard";
    $("#editOrientation").value = s.orientation || "landscape";
    $("#editTags").value = (s.tags || []).join(", ");
    $("#editNotes").value = s.notes || "";
    $("#editStatus").textContent = "";

    const modal = $("#editModal");
    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeEditModal() {
    const modal = $("#editModal");
    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  async function saveEditModal() {
    const id = $("#editSubmissionId").value.trim();
    const payload = {
      id,
      book_abbr: $("#editBookAbbr").value.trim(),
      chapter: Number($("#editChapter").value),
      verse: $("#editVerse").value.trim(),
      citation_ja: $("#editCitationJa").value.trim(),
      verse_text: $("#editVerseText").value.trim(),
      size: $("#editSize").value.trim(),
      orientation: $("#editOrientation").value.trim(),
      tags: $("#editTags").value.trim(),
      notes: $("#editNotes").value.trim(),
    };
    const status = $("#editStatus");
    const saveBtn = $("#editSaveBtn");

    if (!id) {
      status.textContent = "対象IDが不正です。";
      return;
    }
    if (!Number.isInteger(payload.chapter) || payload.chapter < 1) {
      status.textContent = "章は 1 以上の整数で入力してください。";
      return;
    }
    if (!EDIT_SIZE.includes(payload.size)) {
      status.textContent = "サイズの値が不正です。";
      return;
    }
    if (!EDIT_ORIENTATION.includes(payload.orientation)) {
      status.textContent = "向きの値が不正です。";
      return;
    }

    saveBtn.disabled = true;
    status.textContent = "保存中…";
    try {
      const res = await postJson("/api/admin/submission_update.php", payload);
      if (res?.ok) {
        status.textContent = "保存しました。";
        await loadSubs();
        closeEditModal();
      } else {
        status.textContent = "保存に失敗しました。";
      }
    } finally {
      saveBtn.disabled = false;
    }
  }

  async function uploadRevision(id) {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/png,image/jpeg";
    input.addEventListener("change", async () => {
      const file = input.files?.[0];
      if (!file) return;
      if (!/^image\/(png|jpeg)$/.test(file.type)) {
        alert("PNG または JPG を選んでください。");
        return;
      }
      const fd = new FormData();
      fd.append("csrf", state.csrf);
      fd.append("id", id);
      fd.append("action", "revision");
      fd.append("image", file);
      const res = await fetch("/api/admin/submission_action.php", {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) { alert(data.error || "失敗しました"); return; }
      alert("改訂版をアップロードしました。");
      await loadSubs();
    });
    input.click();
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ csrf: state.csrf, ...body }),
      credentials: "same-origin",
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) alert(data.error || "失敗しました");
    return data;
  }

  // ========== Reports ==========
  async function loadReports() {
    const res = await fetch(`/api/admin/reports.php?status=${encodeURIComponent(state.repStatus)}`, {
      credentials: "same-origin",
    });
    const data = await res.json().catch(() => ({}));
    const items = data.items || [];
    const host = $("#repList");
    $("#repEmpty").hidden = items.length > 0;
    host.innerHTML = items.map(renderReport).join("");
    host.querySelectorAll("[data-rep-action]").forEach((btn) =>
      btn.addEventListener("click", () => handleRepAction(btn))
    );
  }

  function renderReport(r) {
    const actions = [];
    if (r.status === "open") {
      actions.push(`<button class="btn-primary" data-rep-action="resolve" data-id="${esc(r.id)}">対応済にする</button>`);
      actions.push(`<button class="btn-ghost" data-rep-action="dismiss" data-id="${esc(r.id)}">却下する</button>`);
    } else {
      actions.push(`<button class="btn-ghost" data-rep-action="reopen" data-id="${esc(r.id)}">再オープン</button>`);
    }
    const STATUS = { open: "未対応", resolved: "対応済", dismissed: "却下" };
    return `
      <article class="adminitem">
        <div class="thumb">${r.file ? `<img src="${esc(r.file)}" loading="lazy" alt="">` : ""}</div>
        <div class="meta">
          <div class="citation">${esc(r.citationJa || "-")}</div>
          <p class="verse">${esc(r.message)}</p>
          <p class="sub">
            <span class="pill is-${r.status === "open" ? "pending" : r.status === "resolved" ? "approved" : "rejected"}">${esc(STATUS[r.status] || r.status)}</span>
            · 報告者: ${esc(r.reporter_email || "(匿名)")}
            · ${esc(r.createdAt)}
          </p>
          <p class="sub">対象 ID: ${esc(r.submission_id)}</p>
        </div>
        <div class="actions">${actions.join("")}</div>
      </article>`;
  }

  async function handleRepAction(btn) {
    const id = btn.dataset.id;
    const action = btn.dataset.repAction;
    if (!id || !action) return;
    await postJson("/api/admin/report_action.php", { id, action });
    await loadReports();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
