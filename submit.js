(() => {
  "use strict";

  const $ = (sel, root = document) => root.querySelector(sel);

  const state = {
    me: null,
    csrf: null,
    books: [],
  };

  async function init() {
    $("#year").textContent = new Date().getFullYear();

    const [meRes, booksRes] = await Promise.all([
      fetch("/api/auth/me.php", { credentials: "same-origin", cache: "no-cache" }),
      fetch("data/books.json", { cache: "no-cache" }),
    ]);
    const meJson = await meRes.json().catch(() => ({}));
    state.me = meJson.user || null;
    state.csrf = meJson.csrf || null;
    state.books = await booksRes.json().catch(() => []);

    if (state.me) {
      showForm();
    } else {
      showLogin();
    }

    bindEvents();
  }

  function showLogin() {
    $("#loginSection").hidden = false;
    $("#formSection").hidden = true;
    $("#doneSection").hidden = true;
  }

  function showForm() {
    $("#loginSection").hidden = true;
    $("#formSection").hidden = false;
    $("#doneSection").hidden = true;
    if (state.me?.email) $("#userEmail").textContent = state.me.email;
    if (state.me?.is_admin) $("#navAdmin").hidden = false;
    $("#navMe").hidden = false;

    populateBooks();
  }

  function populateBooks() {
    const sel = $("#bookSelect");
    sel.innerHTML = '<option value="">選択してください</option>';
    [...state.books].sort((a, b) => a.no - b.no).forEach((b) => {
      const opt = document.createElement("option");
      opt.value = b.abbr;
      opt.textContent = `${b.ja}（${b.en}）`;
      opt.dataset.ja = b.ja;
      sel.appendChild(opt);
    });
  }

  function updateCitation() {
    const sel = $("#bookSelect");
    const ja = sel.selectedOptions[0]?.dataset.ja;
    const chapter = $("#chapterInput").value;
    const verse = $("#verseInput").value;
    if (!ja || !chapter || !verse) return;
    const target = $("#citationInput");
    if (!target.dataset.dirty) {
      target.value = `${ja} ${chapter}章${verse}節`;
    }
  }

  function bindEvents() {
    // Login
    $("#loginSubmit").addEventListener("click", async () => {
      const email = $("#loginEmail").value.trim();
      const status = $("#loginStatus");
      if (!email) {
        status.textContent = "メールアドレスを入力してください。";
        return;
      }
      $("#loginSubmit").disabled = true;
      status.textContent = "送信中…";
      try {
        const res = await fetch("/api/auth/request.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email, redirect: "/submit.html" }),
          credentials: "same-origin",
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || "送信に失敗しました。");
        status.textContent = `${email} にログインリンクを送りました。メールを確認してください（迷惑メールもチェック）。`;
      } catch (err) {
        status.textContent = err.message || "送信に失敗しました。";
      } finally {
        $("#loginSubmit").disabled = false;
      }
    });

    // Logout
    $("#logoutBtn")?.addEventListener("click", async () => {
      await fetch("/api/auth/logout.php", { method: "POST", credentials: "same-origin" });
      location.reload();
    });

    // Book / chapter / verse auto-fill citation
    $("#bookSelect").addEventListener("change", updateCitation);
    $("#chapterInput").addEventListener("input", updateCitation);
    $("#verseInput").addEventListener("input", updateCitation);
    $("#citationInput").addEventListener("input", (e) => {
      e.target.dataset.dirty = "1";
    });

    // File drop
    const drop = $("#fileDrop");
    const input = $("#fileInput");
    const preview = $("#filePreview");
    const info = $("#fileInfo");
    const onFile = (file) => {
      if (!file) return;
      if (!/^image\/(png|jpeg)$/.test(file.type)) {
        showSubmitStatus("PNG または JPG を選んでください。", "error");
        input.value = "";
        return;
      }
      const url = URL.createObjectURL(file);
      preview.src = url;
      info.textContent = `${file.name} · ${(file.size / 1024).toFixed(0)} KB`;
      drop.classList.add("has-file");
    };
    input.addEventListener("change", () => onFile(input.files?.[0]));
    ["dragenter", "dragover"].forEach((ev) =>
      drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add("is-dragover"); })
    );
    ["dragleave", "drop"].forEach((ev) =>
      drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove("is-dragover"); })
    );
    drop.addEventListener("drop", (e) => {
      const file = e.dataTransfer?.files?.[0];
      if (file) {
        // use DataTransfer to set the input value
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        onFile(file);
      }
    });

    // Submit
    $("#submitForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      await submitForm();
    });

    $("#submitAnotherBtn").addEventListener("click", (e) => {
      e.preventDefault();
      $("#submitForm").reset();
      drop.classList.remove("has-file");
      showForm();
    });
  }

  function showSubmitStatus(text, kind) {
    const el = $("#submitStatus");
    el.textContent = text;
    el.style.color =
      kind === "error" ? "#b42318" :
      kind === "success" ? "#2e7d32" : "var(--text-muted)";
  }

  async function submitForm() {
    const file = $("#fileInput").files?.[0];
    if (!file) { showSubmitStatus("画像ファイルを選んでください。", "error"); return; }

    const fd = new FormData();
    fd.append("csrf", state.csrf || "");
    fd.append("image", file);
    fd.append("book_abbr", $("#bookSelect").value);
    fd.append("chapter", $("#chapterInput").value);
    fd.append("verse", $("#verseInput").value.trim());
    fd.append("verse_text", $("#verseTextInput").value.trim());
    fd.append("citation_ja", $("#citationInput").value.trim());
    fd.append("size", $("#sizeSelect").value);
    fd.append("tags", $("#tagsInput").value.trim());
    fd.append("notes", $("#notesInput").value.trim());

    const btn = $("#submitBtn");
    btn.disabled = true;
    showSubmitStatus("送信中…");

    try {
      const res = await fetch("/api/submit.php", {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || "送信に失敗しました。");
      $("#formSection").hidden = true;
      $("#doneSection").hidden = false;
    } catch (err) {
      showSubmitStatus(err.message || "送信に失敗しました。", "error");
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
