tinymce.PluginManager.add("dsmfileinsert", function (editor, url) {
  const apiEndpoint = editor.getParam("dsmfileinsert_api_endpoint", "");
  const fileManagerUrl = editor.getParam("dsmfileinsert_file_manager_url", "");

  // Function to fetch files from API
  const fetchFiles = (endpoint, page = 1, perPage = 8, search = "") => {
    return fetch(
      `${endpoint}?page=${page}&per_page=${perPage}&search=${encodeURIComponent(
        search
      )}`
    )
      .then((response) => response.json())
      .then((data) => {
        return data.data; // Extract the array of files
      })
      .catch((error) => {
        console.error("Error fetching files:", error);
      });
  };

  // Function to open the custom dialog
  const openDialog = () => {
    let currentPage = 1;
    const perPage = 8;
    let searchQuery = "";

    const dialogConfig = {
      title: "Insert File",
      size: "large",
      body: {
        type: "panel",
        items: [
          {
            type: "bar",
            items: [
              {
                type: "input", // Change from 'textbox' to 'input'
                name: "search",
                placeholder: "Search", // placeholder text for the input
                maximized: true,
              },
              {
                type: "button",
                text: "Search",
                name: "searchButton",
              },
            ],
          },
          {
            type: "checkbox",
            name: "insertDescription",
            label: "Insert with description",
          },
          {
            type: "htmlpanel",
            html: `
              <style>
                .file-grid {
                    display: grid !important;
                    grid-template-columns: repeat(1, 1fr); /* Default 1 column */
                    gap: 10px;
                }
            
                /* For large screens (lg) */
                @media (min-width: 992px) {
                    .file-grid {
                        grid-template-columns: repeat(2, 1fr); /* 2 columns */
                    }
                }
            
                /* For extra-large screens (xl) */
                @media (min-width: 1200px) {
                    .file-grid {
                        grid-template-columns: repeat(4, 1fr); /* 4 columns */
                    }
                }
            
                .file-grid-item {
                    width: 100% !important;
                    height: 9rem !important;
                    cursor: pointer !important;
                    border: 1px solid #ccc !important;
                    border-radius: 1rem;
                    padding: 0.5rem !important;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }
            
                .file-grid-item.selected {
                    border: 2px solid #0073aa;
                    background-color: #f0f0f0;
                }
            
                #loading-message {
                    margin-top: 2rem;
                    margin-bottom: 2rem;
                    text-align: center;
                    font-weight: bold;
                }
                .loader {
                  width: 48px !important;
                  height: 48px !important;
                  border: 5px solid #000000 !important;
                  border-bottom-color: transparent !important;
                  border-radius: 50%;
                  display: inline-block;
                  box-sizing: border-box !important;
                  animation: rotation 1s linear infinite;
                }
              
                @keyframes rotation {
                  0% {
                      transform: rotate(0deg);
                  }
                  100% {
                      transform: rotate(360deg);
                  }
                } 
            
                .file-grid-item p {
                    margin: 0;
                    font-size: 1rem;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: -webkit-box;
                    -webkit-line-clamp: 3; /* Limit text to 3 lines */
                    -webkit-box-orient: vertical;
                }
            
                .file-grid-item p:first-of-type {
                    font-size: 2rem;
                    font-weight: bold;
                }

                /* Add styles for the scrollable area */
                #file-grid-container {
                  max-height: 440px; /* Adjust this value as needed */
                  overflow-y: auto;
                }

                /* Ensure the file grid fits within the container */
                .file-grid {
                  height: auto;
                }
            </style>
            
            <div id="file-grid-container">
              <div id="file-grid" class="file-grid"></div>
            </div>
            `,
          },
          {
            type: "htmlpanel",
            html: `<div id="loading-message"><span class="loader"></span></div>`,
          },
        ],
      },
      buttons: [
        {
          type: "custom",
          text: "Previous",
          icon: "arrow-left",
          name: "prev",
          align: "start", // align the button to the left of the dialog footer
        },
        {
          type: "custom",
          text: "Next",
          icon: "arrow-right",
          name: "next",
          align: "start", // align the button to the left of the dialog footer
        },
        {
          type: "custom",
          text: "Upload",
          name: "upload",
          align: "start", // align the button to the left of the dialog footer
          icon: "browse", // will replace the text if configured
          buttonType: "primary", // style the button as a primary button
        },
        {
          type: "cancel",
          text: "Close",
        },
        {
          type: "submit",
          text: "Insert",
          primary: true,
          name: "insert",
          tooltip: "Insert selected file",
        },
      ],
      initialData: {},
      onSubmit: (api) => {
        const data = api.getData();
        const selectedFile = document.querySelector(".file-grid-item.selected");

        if (selectedFile) {
          const file = selectedFile.dataset;
          let html = `<a href="${file.uri}">${
            file.judul
              ? file.judul
              : file.alt
              ? file.alt
              : getFileName(file.uri)
          } (${getFileExtension(file.uri)})</a>`;
          if (data.insertDescription && file.deskripsi) {
            html += `<p>${file.deskripsi}</p>`;
          }
          editor.insertContent(html);
        }
        api.close();
      },
      onAction: (api, details) => {
        // Previous
        if (details.name === "prev" && currentPage > 1) {
          currentPage--;
          loadFiles(api);
        }
        // Next
        else if (details.name === "next") {
          currentPage++;
          loadFiles(api);
        }
        // Search
        else if (details.name === "searchButton") {
          const data = api.getData();
          searchQuery = data.search;
          currentPage = 1; // Reset to first page
          loadFiles(api);
        }
        // Upload
        else if (details.name === "upload") {
          tinymce.activeEditor.windowManager.openUrl({
            title: "Upload",
            url: fileManagerUrl,
            onMessage: (windowApi, details) => {
              // console.log(details);

              // Receiving event from single file upload
              if (details.mceAction === "singleFileUpload") {
                windowApi.close(); // Close the window
              }
            },
            onClose: () => {
              loadFiles(api); // Reload data
            },
          });
        }
      },
    };

    const dialogApi = editor.windowManager.open(dialogConfig);

    const loadFiles = (api) => {
      const loadingMessage = document.getElementById("loading-message");
      const fileGrid = document.getElementById("file-grid");

      loadingMessage.style.display = "block"; // Show loading message
      fileGrid.style.display = "none"; // Hide file grid

      fetchFiles(apiEndpoint, currentPage, perPage, searchQuery).then(
        (files) => {
          loadingMessage.style.display = "none"; // Hide loading message
          fileGrid.style.display = "flex"; // Show file grid
          renderFiles(files);
        }
      );
    };

    const renderFiles = (files) => {
      const fileGrid = document.getElementById("file-grid");
      fileGrid.innerHTML = files
        .map(
          (file) =>
            `
              <div class="file-grid-item" 
                data-uri="${file.uri}" 
                data-judul="${file.judul}" 
                data-alt="${file.alt}" 
                data-deskripsi="${file.deskripsi || ""}">
                
                <p>${getFileExtension(file.uri)}</p>
                
                <p>${
                  file.judul
                    ? file.judul
                    : file.alt
                    ? file.alt
                    : getFileName(file.uri)
                }</p>
                
              </div>
            `
        )
        .join("");

      document.querySelectorAll(".file-grid-item").forEach((item) => {
        item.addEventListener("click", () => {
          document
            .querySelectorAll(".file-grid-item")
            .forEach((i) => i.classList.remove("selected"));
          item.classList.add("selected");
        });
      });
    };

    const getFileExtension = (uri) => uri.split(".").pop().split(/\#|\?/)[0]; // To extract the file extension from uri
    const getFileName = (uri) => uri.split("/").pop().split(/\#|\?/)[0];

    loadFiles(dialogApi); // Load files when the dialog opens
  };

  // Add the button to the toolbar
  editor.ui.registry.addButton("dsmfileinsert", {
    text: "File",
    icon: "link",
    onAction: openDialog,
  });

  return {
    getMetadata: () => ({
      name: "DSM File Insert",
      url: "#",
    }),
  };
});
