tinymce.PluginManager.add("dsmgallery", function (editor, url) {
  const apiEndpoint = editor.getParam("dsmgallery_api_endpoint", "");
  const galleryUrl = editor.getParam("dsmgallery_gallery_url", "");

  // Function to fetch images from API
  const fetchImages = (endpoint, page = 1, perPage = 10, search = "") => {
    return fetch(
      `${endpoint}?page=${page}&per_page=${perPage}&search=${encodeURIComponent(
        search
      )}`
    )
      .then((response) => response.json())
      .then((data) => {
        return data.data; // Extract the array of images
      })
      .catch((error) => {
        console.error("Error fetching images:", error);
      });
  };

  // Function to open the custom dialog
  const openDialog = () => {
    let currentPage = 1;
    const perPage = 10;
    let searchQuery = "";

    const dialogConfig = {
      title: "Insert Image from Gallery",
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
            .image-grid {
                display: grid !important;
                grid-template-columns: repeat(1, 1fr); /* Default 1 column */
                gap: 10px;
            }
        
            /* For large screens (lg) */
            @media (min-width: 992px) {
                .image-grid {
                    grid-template-columns: repeat(3, 1fr); /* 3 columns */
                }
            }
        
            /* For extra-large screens (xl) */
            @media (min-width: 1200px) {
                .image-grid {
                    grid-template-columns: repeat(5, 1fr); /* 5 columns */
                }
            }
        
            .image-grid-item {
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
        
            .image-grid-item.selected {
                border: 2px solid #0073aa;
                background-color: #f0f0f0;
            }

            .image-grid-item img {
              width: 100% !important;
              height: 100% !important;
              object-fit: cover; 
              border-radius: 1rem;
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

            /* Add styles for the scrollable area */
            #image-grid-container {
              max-height: 440px; /* Adjust this value as needed */
              overflow-y: auto;
            }

            /* Ensure the image grid fits within the container */
            .image-grid {
              height: auto;
            }
        </style>
    
        <div id="image-grid-container">
          <div id="image-grid" class="image-grid"></div>
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
          align: "start",
        },
        {
          type: "custom",
          text: "Gallery",
          name: "gallery",
          align: "start",
          icon: "gallery", // will replace the text if configured
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
        },
      ],
      initialData: {},
      onSubmit: (api) => {
        const data = api.getData();
        const selectedImage = document.querySelector(
          ".image-grid-item.selected"
        );

        if (selectedImage) {
          const img = selectedImage.dataset;
          let html = `<img src="${img.uri}" alt="${img.alt}" title="${img.title}" />`;
          if (data.insertDescription && img.deskripsi) {
            html += `<p>${img.deskripsi}</p>`;
          }
          editor.insertContent(html);
        }
        api.close();
      },
      onAction: (api, details) => {
        if (details.name === "prev" && currentPage > 1) {
          currentPage--;
          loadImages(api);
        } else if (details.name === "next") {
          currentPage++;
          loadImages(api);
        } else if (details.name === "searchButton") {
          const data = api.getData();
          searchQuery = data.search;
          currentPage = 1; // Reset to first page
          loadImages(api);
        } else if (details.name === "gallery") {
          tinymce.activeEditor.windowManager.openUrl({
            title: "Gallery",
            url: galleryUrl,
            onClose: () => {
              loadImages(api); // Reload data
            },
          });
        }
      },
    };

    const dialogApi = editor.windowManager.open(dialogConfig);

    const loadImages = (api) => {
      const loadingMessage = document.getElementById("loading-message");
      const imageGrid = document.getElementById("image-grid");

      loadingMessage.style.display = "block"; // Show loading message
      imageGrid.style.display = "none"; // Hide image grid

      fetchImages(apiEndpoint, currentPage, perPage, searchQuery).then(
        (images) => {
          loadingMessage.style.display = "none"; // Hide loading message
          imageGrid.style.display = "flex"; // Show image grid
          renderImages(images);
        }
      );
    };

    const renderImages = (images) => {
      const imageGrid = document.getElementById("image-grid");
      imageGrid.innerHTML = images
        .map(
          (image) => `
                <div class="image-grid-item" data-uri="${
                  image.uri
                }" data-title="${image.judul}" data-alt="${
            image.alt
          }" data-deskripsi="${image.deskripsi || ""}">
                    <img src="${image.uri}" alt="${image.alt}" title="${
            image.judul
          }"/>
                </div>
            `
        )
        .join("");

      document.querySelectorAll(".image-grid-item").forEach((item) => {
        item.addEventListener("click", () => {
          document
            .querySelectorAll(".image-grid-item")
            .forEach((i) => i.classList.remove("selected"));
          item.classList.add("selected");
        });
      });
    };

    loadImages(dialogApi); // Load images when the dialog opens
  };

  // Add the button to the toolbar
  editor.ui.registry.addButton("dsmgallery", {
    text: "Gallery",
    icon: "gallery",
    onAction: openDialog,
  });

  return {
    getMetadata: () => ({
      name: "DSM Gallery",
      url: "#",
    }),
  };
});
