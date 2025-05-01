import i18next from "../admin/translations/I18n.js";
import { config } from "../Config.js";
import tippy from "https://cdn.jsdelivr.net/npm/tippy.js@6.3.7/+esm";

export default class FileManager {
  constructor() {
    // String properties
    this.currentFile = "";
    this.currentPath = "";

    // File-related DOM elements
    this.fileCheckboxes = ".file-checkbox";
    this.fileCheckboxesChecked = ".file-checkbox:checked";
    this.fileEditor = document.getElementById("fileEditor");
    this.fileList = document.getElementById("fileList");

    // Loader elements
    this.loaderBody = document.getElementById("loaderBody");
    this.loaderModal = document.getElementById("loaderModal");

    // Other UI elements
    this.monaco = document.getElementById("monaco");
    this.saveButton = document.getElementById("saveButton");
    this.tableBody = document.querySelector("#hyperTable tbody");

    // Modal elements
    this.viewModal = document.getElementById("viewModal");
    this.viewModalKonten = document.getElementById("viewModalKonten");
    this.viewModalLabel = document.getElementById("viewModalLabel");
  }

  #downloadFile(path) {
    window.location.href =
      `${config.baseUrl + "api/file-manager/download/"}` +
      encodeURIComponent(window.hyper_hexEncode(path));
  }

  #addToClipboard(path, action) {
    let clipboard = {
      files: [path],
      action,
    };

    fetch(`${config.baseUrl + "api/file-manager/set-clipboard/"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(clipboard),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status) {
          // Show success toast
          window.hyper_swal.success(
            `${action.charAt(0).toUpperCase() + action.slice(1)}: ${i18next.t(
              "copiedSuccessfullyReadyToPaste"
            )}`
          );
        } else {
          // Show error toast
          window.hyper_swal.error(
            `${i18next.t("failedToCopy")}: ` + data.error,
            {
              showConfirmButton: true,
            }
          );
        }
      })
      .catch((error) => console.error("Error setting clipboard:", error));
  }

  pasteFiles() {
    fetch(`${config.baseUrl + "api/file-manager/paste/"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        destination: this.currentPath,
      }), // Set the destination path
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status) {
          // Show success toast
          window.hyper_swal.success(`${i18next.t("pastedSuccessfully")}`);
          this.listFiles(this.currentPath); // Refresh the file list
        } else if (data.error) {
          // Show error toast
          window.hyper_swal.error(
            `${i18next.t("failedToPaste")} ` + data.error,
            {
              showConfirmButton: true,
              timer: false,
            }
          );
        }
      })
      .catch((error) => console.error("Error pasting files:", error));
  }

  viewFile(path) {
    this.currentFile = path;

    openModal(this.viewModal); // Open view modal

    if (!this.monaco.classList.contains("is-hidden")) {
      this.monaco.classList.add("is-hidden");
    }

    this.saveButton.onclick = function () {
      this.saveFile(path);
    };

    const fileName = path.split("/").pop(); // Get file extension
    const fileExtension = path.split(".").pop().toLowerCase(); // Get file extension
    const imageUrl =
      `${config.baseUrl + "api/file-manager/view-file/"}` +
      encodeURIComponent(window.hyper_hexEncode(path));

    // UI
    const viewModalKonten = this.viewModalKonten;
    this.viewModalLabel.innerHTML = fileName;

    if (viewModalKonten.classList.contains("is-hidden")) {
      viewModalKonten.classList.remove("is-hidden");
    }

    // Define HTML content based on file extension
    let contentHTML = "";
    let isEditable = false;

    if (
      ["jpg", "jpeg", "png", "gif", "bmp", "webp", "ico"].includes(
        fileExtension
      )
    ) {
      // Display image files
      contentHTML = `<img src="${imageUrl}" class="w-100" alt="Image Preview">`;
    } else if (["mp4", "webm", "ogg"].includes(fileExtension)) {
      // Display video files
      contentHTML = `<video src="${imageUrl}" class="w-100" controls></video>`;
    } else if (["mp3", "wav", "ogg"].includes(fileExtension)) {
      // Display audio files
      contentHTML = `<audio src="${imageUrl}" class="w-100" controls></audio>`;
    } else if (
      [
        "txt",
        "log",
        "md",
        "json",
        "php",
        "js",
        "css",
        "ts",
        "tsx",
        "html",
        "htm",
        "xml",
        "yml",
        "yaml",
        "ini",
        "conf",
        "bat",
        "sh",
        "c",
        "cpp",
        "h",
        "hpp",
        "py",
        "rb",
        "java",
        "cs",
        "swift",
        "rs",
        "go",
        "pl",
        "ps1",
        "svelte",
        "scss",
        "sass",
        "less",
        "sql",
        "r",
        "dockerfile",
        "env",
      ].includes(fileExtension)
    ) {
      // Display text files in editable mode
      fetch(imageUrl)
        .then((response) => response.text())
        .then((text) => {
          isEditable = true; // Set editable to true

          // Update UI
          this.fileEditor.value = `${text}`;

          if (this.monaco.classList.contains("is-hidden")) {
            this.monaco.classList.remove("is-hidden");
          }
          if (!viewModalKonten.classList.contains("is-hidden")) {
            viewModalKonten.classList.add("is-hidden");
          }

          this.saveButton.style.display = "block"; // Show Save button
          openModal(this.viewModal); // Reopen the view modal

          // Retrieve the editor instance by container ID (e.g., "monaco")
          const editor = window.hyper_fileManagerMonaco.editor;

          // Set editor language
          let language = "plaintext";
          switch (fileExtension) {
            case "json":
              language = "javascript";
              break;
            case "htm":
            case "html":
              language = "html";
              break;
            case "php":
              language = "php";
              break;
            case "js":
              language = "javascript";
              break;
            case "css":
              language = "css";
              break;
            case "ts":
            case "tsx":
              language = "typescript";
              break;
            case "xml":
              language = "xml";
              break;
            case "yml":
            case "yaml":
              language = "yaml";
              break;
            case "ini":
            case "conf":
              language = "ini";
              break;
            case "bat":
              language = "bat";
              break;
            case "sh":
              language = "shell";
              break;
            case "c":
            case "h":
              language = "c";
              break;
            case "cpp":
            case "hpp":
              language = "cpp";
              break;
            case "py":
              language = "python";
              break;
            case "rb":
              language = "ruby";
              break;
            case "java":
              language = "java";
              break;
            case "cs":
              language = "csharp";
              break;
            case "swift":
              language = "swift";
              break;
            case "rs":
              language = "rust";
              break;
            case "go":
              language = "go";
              break;
            case "pl":
              language = "perl";
              break;
            case "ps1":
              language = "powershell";
              break;
            case "scss":
            case "sass":
              language = "scss";
              break;
            case "less":
              language = "less";
              break;
            case "sql":
              language = "sql";
              break;
            case "r":
              language = "r";
              break;
            case "md":
              language = "markdown";
              break;
            case "dockerfile":
              language = "dockerfile";
              break;
            default:
              language = "plaintext";
              break;
          }

          // Set the language of the editor
          window.hyper_fileManagerMonaco
            .getMonaco()
            .editor.setModelLanguage(editor.getModel(), language);

          // Set the editor's value to the file content
          editor.getModel().setValue(`${text}`);
        });
      return;
    } else {
      // Other file types, provide a download option
      contentHTML = `<p>${i18next.t("previewUnavailable")}</p>
        <a href="${imageUrl}" class="button is-primary" download>${i18next.t(
        "downloadFile"
      )}</a>`;
    }

    // Insert content and display modal
    this.viewModalKonten.innerHTML = contentHTML;
    this.saveButton.style.display = isEditable ? "block" : "none"; // Show or hide Save button
    openModal(this.viewModal); // Open view modal
  }

  refreshFileList() {
    this.listFiles(this.currentPath);
  }

  listFiles(path = "") {
    this.currentPath = path; // Update the current path

    /* UI: Show loader */
    if (this.loaderBody.classList.contains("is-hidden")) {
      this.loaderBody.classList.remove("is-hidden");
    }

    fetch(
      `${config.baseUrl + "api/file-manager/list-files/"}` +
        encodeURIComponent(window.hyper_hexEncode(path))
    )
      .then(async (response) => {
        if (!response.ok) {
          // If the response isn't OK, extract the JSON error and throw it.
          const errorData = await response.json();
          throw errorData;
        }
        return response.json();
      })
      .then((data) => {
        /* UI: Hide loader */
        if (!this.loaderBody.classList.contains("is-hidden")) {
          this.loaderBody.classList.add("is-hidden");
        }

        if (data.error) {
          throw data; // Will be caught below.
        }

        // Sort folders first:
        data.sort((a, b) => b.is_dir - a.is_dir);

        // Prepare an array of rows.
        // Each row is an array, matching the order of your DataTable's columns:
        // [checkbox, file name (with icon), file size, permissions, modified date, action buttons]
        let rows = [];

        // "Back" button row if in a subfolder (simulate going up one level)
        if (path) {
          const upPath = path.split("/").slice(0, -1).join("/");
          rows.push([
            `<span><i class="fas fa-chevron-left"></i></span>`,
            `<a href="#" class="file-link" data-path="${upPath}" data-type="folder">..</a>`,
            "", // File size column
            "", // Permissions column
            "", // Modified date column
            "", // No action buttons on the back button row
          ]);
        }

        if (data.length === 0) {
          // Show “no file or folder found” message.
          rows.push([
            "",
            `<span class="text-center" style="display:block;">${i18next.t(
              "noFileOrFolderFound"
            )}</span>`,
            "",
            "",
            "",
            "",
          ]);
        } else {
          // Loop through each file/folder and generate a row.
          data.forEach((file) => {
            let icon = file.is_dir
              ? '<i class="far fa-folder"></i>'
              : this.#getIconByExtension(
                  file.name.split(".").pop().toLowerCase()
                );
            let dateModified = file.modified_date || "-";
            let permissions = file.permissions || "-";

            // Build the action buttons depending on file or folder.
            let actionBtns = file.is_dir
              ? `<span class="btn-action-tooltip" data-tippy-content="${i18next.t(
                  "open"
                )}">
                            <button class="button is-primary is-small btn-action" data-action="open" data-path="${
                              file.path
                            }">
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                       </span>`
              : `<span class="btn-action-tooltip" data-tippy-content="${i18next.t(
                  "view"
                )}">
                            <button class="button is-secondary is-small btn-action" data-action="view" data-path="${
                              file.path
                            }">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                       </span>
                       <span class="btn-action-tooltip" data-tippy-content="${i18next.t(
                         "download"
                       )}">
                            <button class="button is-secondary is-small btn-action" data-action="download" data-path="${
                              file.path
                            }">
                                <i class="fa-solid fa-download"></i>
                            </button>
                       </span>`;

            actionBtns += `
                    <span class="btn-action-tooltip" data-tippy-content="${i18next.t(
                      "copy"
                    )}">
                        <button class="button is-secondary is-small btn-action" data-action="copy" data-path="${
                          file.path
                        }">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </span>
                    <span class="btn-action-tooltip" data-tippy-content="${i18next.t(
                      "move"
                    )}">
                        <button class="button is-secondary is-small btn-action" data-action="move" data-path="${
                          file.path
                        }">
                            <i class="fa-solid fa-scissors"></i>
                        </button>
                    </span>
                    <span class="btn-action-tooltip" data-tippy-content="${i18next.t(
                      "rename"
                    )}">
                        <button class="button is-secondary is-small btn-action" data-action="rename" data-path="${
                          file.path
                        }">
                            <i class="fa-solid fa-i-cursor"></i>
                        </button>
                    </span>`;

            // Push the row into our rows array.
            rows.push([
              `<span>${icon}</span>`,
              `<a href="#" class="file-link" data-path="${
                file.path
              }" data-type="${
                file.is_dir ? "folder" : "file"
              }" onclick="event.preventDefault()">
                        ${file.name}
                     </a>`,
              file.size,
              permissions,
              dateModified,
              `<div style="white-space: nowrap;">${actionBtns}</div>`,
            ]);
          });
        }

        // Use the DataTables API to update your table:
        window.hyper_fileManager_table.clear();
        window.hyper_fileManager_table.rows.add(rows);
        window.hyper_fileManager_table.draw();
        window.hyper_fileManager_table.columns.adjust();

        // Initialize or re-initialize tooltips on the new action buttons.
        tippy(".btn-action-tooltip");

        // Attach event listeners for action buttons using event delegation.
        // First event handler for file-link clicks
        const hyperTableTbody = this.tableBody;

        // Remove existing click event listeners for a.file-link
        const oldFileLinkHandler = hyperTableTbody._fileLinkHandler;
        if (oldFileLinkHandler) {
          hyperTableTbody.removeEventListener("click", oldFileLinkHandler);
        }

        // New click event handler for a.file-link
        const newFileLinkHandler = (event) => {
          const fileLink = event.target.closest("a.file-link");
          if (fileLink) {
            event.preventDefault();
            const filePath = fileLink.getAttribute("data-path");
            const type = fileLink.getAttribute("data-type");
            if (type === "folder") {
              this.listFiles(filePath);
            } else {
              this.viewFile(filePath);
            }
          }
        };

        // Add the new handler and store a reference
        hyperTableTbody.addEventListener("click", newFileLinkHandler);
        hyperTableTbody._fileLinkHandler = newFileLinkHandler;

        // Second event handler for btn-action clicks
        // Remove existing click event listeners for .btn-action
        const oldBtnActionHandler = hyperTableTbody._btnActionHandler;
        if (oldBtnActionHandler) {
          hyperTableTbody.removeEventListener("click", oldBtnActionHandler);
        }

        // New click event handler for .btn-action
        const newBtnActionHandler = (event) => {
          const btnAction = event.target.closest(".btn-action");
          if (btnAction) {
            event.preventDefault();
            event.stopPropagation(); // Prevent bubbling to parent elements

            const action = btnAction.getAttribute("data-action");
            const filePath = btnAction.getAttribute("data-path");

            switch (action) {
              case "open":
                this.listFiles(filePath);
                break;
              case "view":
                this.viewFile(filePath);
                break;
              case "download":
                this.#downloadFile(filePath);
                break;
              case "copy":
                this.#addToClipboard(filePath, "copy");
                break;
              case "move":
                this.#addToClipboard(filePath, "move");
                break;
              case "rename":
                this.#renameFile(filePath);
                break;
            }
          }
        };

        // Add the new handler and store a reference
        hyperTableTbody.addEventListener("click", newBtnActionHandler);
        hyperTableTbody._btnActionHandler = newBtnActionHandler;
      })
      .catch((error) => {
        /* UI: Hide loader */
        if (!this.loaderBody.classList.contains("is-hidden")) {
          this.loaderBody.classList.add("is-hidden");
        }
        // Display error using your SweetAlert2 error handler.
        window.hyper_swal.error(
          error.message || "An unexpected error occurred",
          {
            showConfirmButton: true,
            timer: false,
          }
        );
      });
  }

  createFile() {
    window.hyper_swal
      .prompt({
        title: `${i18next.t("enterNewFileNameWithExtension")}`,
        preConfirm: (fileName) => {
          if (!fileName) {
            window.hyper_swal
              .get()
              .showValidationMessage(
                `${i18next.t("failedToCreateFile")}: ${i18next.t(
                  "inputRequired"
                )}`
              );
            return;
          }
          return fetch(`${config.baseUrl + "api/file-manager/create-file/"}`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              path: this.currentPath,
              fileName: fileName,
            }),
          })
            .then((response) => response.json())
            .then((data) => {
              if (!data.status) {
                window.hyper_swal
                  .get()
                  .showValidationMessage(
                    `${i18next.t("failedToCreateFile")}: ` + data.error
                  );
              }
              return data;
            })
            .catch((error) => {
              window.hyper_swal
                .get()
                .showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !window.hyper_swal.get().isLoading(),
      })
      .then((result) => {
        if (result.isConfirmed && result.value.status) {
          window.hyper_swal.success(result.value.status);
          this.listFiles(this.currentPath); // Refresh the list to show the new file
        }
      });
  }

  createFolder() {
    window.hyper_swal
      .prompt({
        title: `${i18next.t("enterNewFolderName")}`,
        preConfirm: (folderName) => {
          if (!folderName) {
            window.hyper_swal
              .get()
              .showValidationMessage(
                `${i18next.t("failedToCreateFolder")}: ${i18next.t(
                  "inputRequired"
                )}`
              );
            return;
          }
          return fetch(
            `${config.baseUrl + "api/file-manager/create-folder/"}`,
            {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                path: this.currentPath,
                folderName: folderName,
              }),
            }
          )
            .then((response) => response.json())
            .then((data) => {
              if (!data.status) {
                window.hyper_swal
                  .get()
                  .showValidationMessage(
                    `${i18next.t("failedToCreateFolder")}: ` + data.error
                  );
              }
              return data;
            })
            .catch((error) => {
              window.hyper_swal
                .get()
                .showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !window.hyper_swal.get().isLoading(),
      })
      .then((result) => {
        if (result.isConfirmed && result.value.status) {
          window.hyper_swal.success(result.value.status);
          this.listFiles(this.currentPath); // Refresh the list to show the new folder
        }
      });
  }

  #renameFile(oldPath) {
    // Extract the filename from oldPath
    const oldFileName = oldPath.split("/").pop();

    window.hyper_swal
      .prompt({
        title: `${i18next.t("rename")}`,
        preConfirm: (newName) => {
          if (!newName) {
            window.hyper_swal
              .get()
              .showValidationMessage(
                `${i18next.t("failedToRenameFile")}: ${i18next.t(
                  "inputRequired"
                )}`
              );
            return;
          }
          return fetch(`${config.baseUrl + "api/file-manager/rename/"}`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              oldPath: oldPath,
              newName: newName,
            }),
          })
            .then((response) => response.json())
            .then((data) => {
              if (!data.status) {
                window.hyper_swal
                  .get()
                  .showValidationMessage(
                    `${i18next.t("failedToRenameFile")}: ` + data.error
                  );
              }
              return data;
            })
            .catch((error) => {
              window.hyper_swal
                .get()
                .showValidationMessage(`Request failed: ${error}`);
            });
        },
        allowOutsideClick: () => !window.hyper_swal.get().isLoading(),
      })
      .then((result) => {
        if (result.isConfirmed && result.value.status) {
          window.hyper_swal.success(result.value.status);
          this.listFiles(this.currentPath); // Refresh the list to show renamed file
        }
      });
  }

  // Helper function to get icon by file extension
  #getIconByExtension(ext) {
    switch (ext) {
      case "jpg":
      case "jpeg":
      case "png":
      case "gif":
      case "webp":
        return '<i class="fas fa-file-image"></i>';
      case "mp4":
      case "mkv":
      case "webm":
        return '<i class="fas fa-file-video"></i>';
      case "mp3":
      case "wav":
        return '<i class="fas fa-file-audio"></i>';
      case "pdf":
        return '<i class="fas fa-file-pdf"></i>';
      case "doc":
      case "docx":
        return '<i class="fas fa-file-word"></i>';
      case "xls":
      case "xlsx":
        return '<i class="fas fa-file-excel"></i>';
      case "ppt":
      case "pptx":
        return '<i class="fas fa-file-powerpoint"></i>';
      case "zip":
      case "rar":
      case "7z":
        return '<i class="fas fa-file-archive"></i>';
      case "txt":
      case "md":
      case "log":
        return '<i class="fas fa-file-alt"></i>';
      case "js":
      case "css":
      case "html":
      case "php":
        return '<i class="fas fa-file-code"></i>';
      default:
        return '<i class="fas fa-file"></i>';
    }
  }

  // Save button functionality
  saveFile(path) {
    /* UI */
    if (this.loaderModal.classList.contains("is-hidden")) {
      this.loaderModal.classList.remove("is-hidden");
    }
    /* End of UI */

    const updatedContent = this.fileEditor.value;
    fetch(`${config.baseUrl + "api/file-manager/save-file/"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        path: encodeURIComponent(window.hyper_hexEncode(path)), // Encode the file path
        content: updatedContent,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        /* UI */
        if (!this.loaderModal.classList.contains("is-hidden")) {
          this.loaderModal.classList.add("is-hidden");
        }
        /* End of UI */

        if (data.success) {
          // Show success toast
          window.hyper_swal.success(`${i18next.t("fileSavedSuccessfully")}`);
        } else {
          // Show error toast
          window.hyper_swal.success(
            `${i18next.t("failedToSaveFile")}: ` + data.error,
            {
              showConfirmButton: true,
            }
          );
        }
      });
  }

  deleteSelectedFiles() {
    const selectedFiles = this.#getSelectedFiles();

    if (selectedFiles.length === 0) {
      // Show error toast
      window.hyper_swal.error(`${i18next.t("selectFilesToDelete")}`);
      return;
    }

    window.hyper_swal
      .confirm({
        text: i18next.t("deletedItemsCannotBeRecovered"),
      })
      .then((result) => {
        if (result.isConfirmed) {
          // Request item deletion
          fetch(`${config.baseUrl + "api/file-manager/delete-files/"}`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              files: selectedFiles,
            }),
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.status) {
                // Show success alert dialog
                window.hyper_swal.success(
                  `${i18next.t("deletedSuccessfully")}`
                );
                this.listFiles(this.currentPath); // Refresh file list
              } else if (data.error) {
                // Show error alert dialog
                window.hyper_swal.success(
                  `${i18next.t("failedToDelete")}: ` + data.error,
                  {
                    showConfirmButton: true,
                    timer: false,
                  }
                );
              }
            })
            .catch((error) => console.error("Error deleting files:", error));
        }
      });
  }

  #getSelectedFiles() {
    return Array.from(this.tableBody.querySelectorAll("tr.selected")).map(
      (row) => {
        return row.querySelector(".file-link").getAttribute("data-path");
      }
    );
  }

  compressSelectedFiles() {
    const selectedFiles = this.#getSelectedFiles();

    if (selectedFiles.length === 0) {
      window.hyper_swal.error(`${i18next.t("selectFileToCompressZIP")}`);
      return;
    }

    /* UI */
    if (this.loaderBody.classList.contains("is-hidden")) {
      this.loaderBody.classList.remove("is-hidden");
    }
    /* End of UI */

    fetch(`${config.baseUrl + "api/file-manager/compress/"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        files: selectedFiles,
        path: this.currentPath,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        /* UI */
        if (!this.loaderBody.classList.contains("is-hidden")) {
          this.loaderBody.classList.add("is-hidden");
        }
        /* End of UI */

        if (data.status) {
          window.hyper_swal.success(`${i18next.t("successfullyCompressed")}`, {
            text: data.archive,
          });
          this.listFiles(this.currentPath); // Refresh list to show new zip file
        } else {
          window.hyper_swal.error(`${i18next.t("error")}`, {
            text: `${i18next.t("failedToCompressFile")}: ` + data.error,
          });
        }
      })
      .catch((error) => console.error("Error compressing files:", error));
  }

  extractSelectedFiles() {
    const selectedFiles = this.#getSelectedFiles();

    if (selectedFiles.length !== 1 || !selectedFiles[0].endsWith(".zip")) {
      window.hyper_swal.error(`${i18next.t("selectZipFileToEctract")}`);
      return;
    }

    /* UI */
    if (this.loaderBody.classList.contains("is-hidden")) {
      this.loaderBody.classList.remove("is-hidden");
    }
    /* End of UI */

    fetch(`${config.baseUrl + "api/file-manager/extract/"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        path: selectedFiles[0],
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        /* UI */
        if (!this.loaderBody.classList.contains("is-hidden")) {
          this.loaderBody.classList.add("is-hidden");
        }
        /* End of UI */

        if (data.status) {
          window.hyper_swal.success(`${i18next.t("successfullyExtracted")}`);
          this.listFiles(this.currentPath); // Refresh to show extracted files
        } else {
          window.hyper_swal.error(
            `${i18next.t("failedToExtractFile")}: ` + data.error
          );
        }
      })
      .catch((error) => console.error("Error extracting file:", error));
  }

  copySelectedFiles() {
    const selectedFiles = this.#getSelectedFiles();
    if (selectedFiles.length === 0) {
      // Show error toast
      window.hyper_swal.error(`${i18next.t("selectFilesToCopy")}`);
      return;
    }
    this.#setClipboard(selectedFiles, "copy");
  }

  moveSelectedFiles() {
    const selectedFiles = this.#getSelectedFiles();
    if (selectedFiles.length === 0) {
      // Show error toast
      window.hyper_swal.error(`${i18next.t("selectFilesToMove")}`);
      return;
    }
    this.#setClipboard(selectedFiles, "move");
  }

  #setClipboard(files, action) {
    fetch(`${config.baseUrl + "api/file-manager/set-clipboard/"}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        files,
        action,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status) {
          // Show success toast
          window.hyper_swal.success(
            `${action.charAt(0).toUpperCase() + action.slice(1)}: ${i18next.t(
              "copiedSuccessfullyReadyToPaste"
            )}`
          );
        } else {
          // Show error toast
          window.hyper_swal.error(
            `${i18next.t("failedToCopy")}: ` + data.error,
            {
              showConfirmButton: true,
            }
          );
        }
      })
      .catch((error) => console.error("Error setting clipboard:", error));
  }
}
