const input = document.getElementById("markdown-input");
const preview = document.getElementById("markdown-preview");
const copyButton = document.getElementById("copy-markdown");
const downloadButton = document.getElementById("download-md");
const clearButton = document.getElementById("clear-preview");
const statusMessage = document.getElementById("status-message");
const runChecksButton = document.getElementById("run-checks");
const importButton = document.getElementById("import-button");
const importInput = document.getElementById("import-md");
const exportHtmlButton = document.getElementById("export-html");

const STORAGE_KEY = "hedgedoc-draft";
let warnedFallback = false;

const defaultMarkdown = `# Welcome to HedgeDoc Workspace

Write **Markdown** on the left and see updates instantly on the right.

## Quick shortcuts
- **Bold**: \\*\\*bold\\*\\*
- **Italic**: \\*italic\\*
- **Code**: \\`inline code\\`

> Tip: Paste your meeting notes or docs here and export when you're ready.

\`\`\`js
const hedgedoc = "Collaborate with confidence";
console.log(hedgedoc);
\`\`\`
`;

const renderer = new marked.Renderer();

marked.setOptions({
  gfm: true,
  breaks: true,
  renderer,
});

const setStatus = (message, tone = "muted") => {
  statusMessage.textContent = message;
  statusMessage.dataset.tone = tone;
};

const escapeHtml = (value) =>
  value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");

const formatInline = (text) => {
  let formatted = text;
  formatted = formatted.replace(/`([^`]+)`/g, "<code>$1</code>");
  formatted = formatted.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  formatted = formatted.replace(/\*([^*]+)\*/g, "<em>$1</em>");
  formatted = formatted.replace(
    /\[([^\]]+)\]\(([^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
  );
  return formatted;
};

const simpleMarkdown = (text) => {
  const lines = text.split(/\n/);
  const output = [];
  let inList = false;
  let inCode = false;
  let codeBuffer = [];

  const closeList = () => {
    if (inList) {
      output.push("</ul>");
      inList = false;
    }
  };

  const flushCode = () => {
    if (codeBuffer.length) {
      output.push(
        `<pre><code>${escapeHtml(codeBuffer.join("\n"))}</code></pre>`
      );
      codeBuffer = [];
    }
  };

  lines.forEach((line) => {
    if (line.trim().startsWith("```")) {
      if (inCode) {
        inCode = false;
        flushCode();
      } else {
        closeList();
        inCode = true;
      }
      return;
    }

    if (inCode) {
      codeBuffer.push(line);
      return;
    }

    if (/^- /.test(line)) {
      if (!inList) {
        output.push("<ul>");
        inList = true;
      }
      const content = formatInline(escapeHtml(line.replace(/^- /, "")));
      output.push(`<li>${content}</li>`);
      return;
    }

    closeList();

    if (/^### /.test(line)) {
      output.push(`<h3>${formatInline(escapeHtml(line.slice(4)))}</h3>`);
      return;
    }
    if (/^## /.test(line)) {
      output.push(`<h2>${formatInline(escapeHtml(line.slice(3)))}</h2>`);
      return;
    }
    if (/^# /.test(line)) {
      output.push(`<h1>${formatInline(escapeHtml(line.slice(2)))}</h1>`);
      return;
    }
    if (/^> /.test(line)) {
      output.push(
        `<blockquote>${formatInline(escapeHtml(line.slice(2)))}</blockquote>`
      );
      return;
    }
    if (line.trim() === "") {
      output.push("<br />");
      return;
    }
    output.push(`<p>${formatInline(escapeHtml(line))}</p>`);
  });

  closeList();
  flushCode();

  return output.join("\n");
};

const renderMarkdown = (raw) => {
  if (typeof marked !== "undefined") {
    return marked.parse(raw);
  }
  if (!warnedFallback) {
    setStatus("CDN unavailable: using basic preview mode.", "muted");
    warnedFallback = true;
  }
  return simpleMarkdown(raw);
};

const sanitizeHtml = (html) => {
  if (typeof DOMPurify !== "undefined") {
    return DOMPurify.sanitize(html);
  }
  return html;
};

const updatePreview = () => {
  const raw = input.value;
  const html = sanitizeHtml(renderMarkdown(raw));
  preview.innerHTML = html;
};

const saveDraft = () => {
  localStorage.setItem(STORAGE_KEY, input.value);
  setStatus("Draft saved locally.", "muted");
};

const loadDraft = () => {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved) {
    input.value = saved;
    updatePreview();
    setStatus("Loaded saved draft.", "muted");
  } else {
    input.value = defaultMarkdown;
    updatePreview();
    setStatus("Preview ready", "muted");
  }
};

const insertWrappedText = (before, after = before) => {
  const start = input.selectionStart;
  const end = input.selectionEnd;
  const text = input.value;
  const selected = text.slice(start, end) || "";
  const updated =
    text.slice(0, start) + before + selected + after + text.slice(end);
  input.value = updated;
  input.focus();
  const cursor = start + before.length + selected.length + after.length;
  input.setSelectionRange(cursor, cursor);
  updatePreview();
};

const insertLinePrefix = (prefix) => {
  const start = input.selectionStart;
  const text = input.value;
  const before = text.slice(0, start);
  const after = text.slice(start);
  const updated = `${before}${prefix}${after}`;
  input.value = updated;
  input.focus();
  input.setSelectionRange(start + prefix.length, start + prefix.length);
  updatePreview();
};

loadDraft();

let saveTimeout;
input.addEventListener("input", () => {
  updatePreview();
  clearTimeout(saveTimeout);
  saveTimeout = setTimeout(saveDraft, 400);
});

copyButton.addEventListener("click", async () => {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(input.value);
    } else {
      const fallback = document.createElement("textarea");
      fallback.value = input.value;
      document.body.appendChild(fallback);
      fallback.select();
      document.execCommand("copy");
      document.body.removeChild(fallback);
    }
    copyButton.textContent = "Copied!";
    setStatus("Markdown copied to clipboard.", "success");
    setTimeout(() => {
      copyButton.textContent = "Copy Markdown";
    }, 1500);
  } catch (error) {
    setStatus("Clipboard failed. Please copy manually.", "error");
  }
});

downloadButton.addEventListener("click", () => {
  const blob = new Blob([input.value], { type: "text/markdown" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = "notes.md";
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
  setStatus("Markdown downloaded.", "success");
});

clearButton.addEventListener("click", () => {
  input.value = "";
  updatePreview();
  input.focus();
  localStorage.removeItem(STORAGE_KEY);
  setStatus("Editor cleared.", "muted");
});

importButton.addEventListener("click", () => {
  importInput.click();
});

importInput.addEventListener("change", async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  try {
    const text = await file.text();
    input.value = text;
    updatePreview();
    saveDraft();
    setStatus(`Imported ${file.name}.`, "success");
  } catch (error) {
    setStatus("Import failed. Try again.", "error");
  } finally {
    importInput.value = "";
  }
});

exportHtmlButton.addEventListener("click", () => {
  const html = sanitizeHtml(renderMarkdown(input.value));
  const blob = new Blob([html], { type: "text/html" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = "notes.html";
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
  setStatus("HTML exported.", "success");
});

runChecksButton.addEventListener("click", () => {
  const checks = [
    { name: "Marked loaded", pass: typeof marked !== "undefined" },
    { name: "DOMPurify loaded", pass: typeof DOMPurify !== "undefined" },
    { name: "Preview element", pass: !!preview },
    { name: "Clipboard API", pass: !!navigator.clipboard?.writeText },
    { name: "Local storage", pass: typeof localStorage !== "undefined" },
  ];
  const failed = checks.filter((check) => !check.pass);
  if (failed.length === 0) {
    setStatus("All checks passed.", "success");
  } else {
    setStatus(
      `Checks failed: ${failed.map((check) => check.name).join(", ")}`,
      "error"
    );
  }
});

document.querySelectorAll("[data-action]").forEach((button) => {
  button.addEventListener("click", () => {
    switch (button.dataset.action) {
      case "bold":
        insertWrappedText("**");
        break;
      case "italic":
        insertWrappedText("*");
        break;
      case "code":
        insertWrappedText("`", "`");
        break;
      case "quote":
        insertLinePrefix("> ");
        break;
      case "list":
        insertLinePrefix("- ");
        break;
      default:
        break;
    }
  });
});

document.addEventListener("keydown", (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key === "s") {
    event.preventDefault();
    downloadButton.click();
  }

  if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
    event.preventDefault();
    if (document.activeElement === input) {
      preview.focus();
    } else {
      input.focus();
    }
  }
});
