const input = document.getElementById("markdown-input");
const preview = document.getElementById("markdown-preview");
const copyButton = document.getElementById("copy-markdown");
const downloadButton = document.getElementById("download-md");
const clearButton = document.getElementById("clear-preview");
const statusMessage = document.getElementById("status-message");
const runChecksButton = document.getElementById("run-checks");

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

const updatePreview = () => {
  const raw = input.value;
  const html = marked.parse(raw);
  preview.innerHTML = DOMPurify.sanitize(html);
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

input.value = defaultMarkdown;
updatePreview();
setStatus("Preview ready");

input.addEventListener("input", updatePreview);

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
});

clearButton.addEventListener("click", () => {
  input.value = "";
  updatePreview();
  input.focus();
  setStatus("Editor cleared.", "muted");
});

runChecksButton.addEventListener("click", () => {
  const checks = [
    { name: "Marked loaded", pass: typeof marked !== "undefined" },
    { name: "DOMPurify loaded", pass: typeof DOMPurify !== "undefined" },
    { name: "Preview element", pass: !!preview },
    { name: "Clipboard API", pass: !!navigator.clipboard?.writeText },
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
