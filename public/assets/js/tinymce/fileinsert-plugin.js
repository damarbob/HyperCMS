tinymce.PluginManager.add("fileinsert", (editor, url) => {
  const fileManagerUrl = editor.getParam(
    "fileinsert_file_manager_url",
    "/admin/file-manager/"
  );
  const fileViewerUrl = editor.getParam(
    "fileinsert_file_viewer_url",
    "/api/file-server/serve/"
  );

  const openDialog = () => {
    tinymce.activeEditor.windowManager.openUrl({
      title: "Insert file",
      url: fileManagerUrl,
      onMessage: (windowApi, details) => {
        // console.log(details);

        // Receiving event from single file upload
        if (details.action && details.action === "filesSelected") {
          windowApi.close(); // Close the window

          if (details.data && Array.isArray(details.data)) {
            details.data.forEach((file) => {
              insertFileContent(editor, file);
            });
          }

          // editor.insertContent(JSON.stringify(details.data));
        }
      },
      onClose: () => {},
    });
  };

  const insertFileContent = (editor, filePath) => {
    // Extract the file name from the path.
    const fileName = filePath.split("/").pop();

    // Get the file extension (ensuring lowercase for consistency).
    const fileExt = fileName.split(".").pop().toLowerCase();

    const renderableExtensions = [
      "jpg",
      "jpeg",
      "png",
      "gif",
      "svg",
      "webp",
      "mp4",
      "webm",
      "ogg",
    ];

    if (renderableExtensions.includes(fileExt)) {
      // Insert a media element.
      if (fileExt === "svg") {
        editor.insertContent(
          `<img src="${
            fileViewerUrl + encodeURIComponent(window.hyper_hexEncode(filePath))
          }" alt="${fileName}" />`
        );
      } else if (["mp4", "webm", "ogg"].includes(fileExt)) {
        editor.insertContent(`
          <video controls>
            <source src="${
              fileViewerUrl +
              encodeURIComponent(window.hyper_hexEncode(filePath))
            }" type="video/${fileExt}">
            Your browser does not support the video tag.
          </video>
        `);
      } else {
        editor.insertContent(
          `<img src="${
            fileViewerUrl + encodeURIComponent(window.hyper_hexEncode(filePath))
          }" alt="${fileName}" />`
        );
      }
    } else {
      // Insert a hyperlink for non-media or unrecognized file types.
      editor.insertContent(
        `<a href="${
          fileViewerUrl + encodeURIComponent(window.hyper_hexEncode(filePath))
        }">${fileName}</a>`
      );
    }
  };

  editor.ui.registry.addButton("fileinsert", {
    tooltip: "Hyper File Insert",
    icon: "folder",
    onAction: openDialog,
  });

  return {
    getMetadata: () => ({
      name: "Hyper File Insert",
      url: "https://dsm.my.id/",
    }),
  };
});
