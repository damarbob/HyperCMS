import $ from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";
import "https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.2/jquery-ui.min.js";
import Sortable from "https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/+esm";
import { StateManager } from "./StateManager.js";
import { ModalManager } from "./ModalManager.js";
import { DataManager } from "./DataManager.js";

export class ComparisonTable {
  constructor(container) {
    this.hConfig = window.hyper?.config;

    // Check if hyperConfig is available
    if (!this.hConfig) {
      console.error("Hyper configuration not found, aborting initialization.");
      return;
    }

    // Import language
    this.langAdmin = window.hyper?.lang.Admin; // Language configuration for Admin
    this.lang = window.hyper?.lang.Dc; // Language configuration for Data Comparison

    this.baseUrl = this.hConfig.baseUrl;
    this.container = $(container);
    this.entries = undefined;

    // Initialize managers
    this.modalManager = new ModalManager(this);
    this.dataManager = new DataManager(this);

    this.initUI();
    this.initStateManagement();
    this.initMutationObserver();
    this.bindEvents();
    this.dataManager.initDataSources();

    // Initialize state manager
    this.stateManager = new StateManager(this);
    this.stateManager.loadStateListFromSettings({
      onComplete: () => {
        // Load last used state
        this.stateManager.loadLastUsedState({
          onComplete: () => {
            this.dataManager.fetchCellData();
          },
        });
      },
      onError: (error) => {
        console.error("Error loading states:", error);
      },
    });

    this.startObserving();
  }

  initUI() {
    // this.isLoadingState = false;
    this.container
      .empty()
      .addClass("comparison-container")
      .append(
        $("<div>", {
          class: "field is-grouped is-grouped-multiline",
        }).append(
          (this.addEntryBtn = $("<button>", {
            id: "addEntryBtn",
            class: "button is-primary",
          }).append(
            $("<span>", {
              class: "icon",
            }).append(
              $("<i>", {
                class: "fa-solid fa-plus",
              })
            ),
            $("<span>", {
              text: this.langAdmin.entry,
            })
          )),
          // Undo and redo buttons
          $("<div>", {
            class: "control",
          }).append(
            $("<div>", {
              class: "buttons has-addons",
            }).append(
              (this.undoBtn = $("<button>", {
                id: "undoBtn",
                class: "button",
              }).append(
                $("<span>", {
                  class: "icon",
                  title: this.langAdmin.undo,
                }).append(
                  $("<i>", {
                    class: "fa-solid fa-rotate-left",
                  })
                )
              )),
              (this.redoBtn = $("<button>", {
                id: "redoBtn",
                class: "button",
                title: this.langAdmin.redo,
              }).append(
                $("<span>", {
                  class: "icon",
                }).append(
                  $("<i>", {
                    class: "fa-solid fa-rotate-right",
                  })
                )
              ))
            )
          ),

          // Save and save as buttons
          $("<div>", {
            class: "control",
          }).append(
            $("<div>", {
              class: "buttons has-addons",
            }).append(
              (this.saveBtn = $("<button>", {
                id: "saveTableState",
                class: "button",
              }).append(
                $("<span>", {
                  class: "icon",
                  title: this.langAdmin.save,
                }).append(
                  $("<i>", {
                    class: "fa-solid fa-save",
                  })
                )
              )),
              (this.saveAsBtn = $("<button>", {
                id: "saveAsBtn",
                class: "button",
                title: this.langAdmin.saveAs,
              }).append(
                $("<span>", {
                  class: "icon",
                }).append(
                  $("<i>", {
                    class: "fa-solid fa-file-export",
                  })
                )
              ))
            )
          ),

          // Load and delete state buttons
          $("<div>", {
            class: "field has-addons",
          }).append(
            $("<div>", {
              class: "control",
            }).append(
              (this.loadStateBtn = $("<button>", {
                id: "loadStateBtn",
                class: "button is-success",
                title: this.lang.loadState,
              }).append(
                $("<span>", {
                  class: "icon",
                }).append(
                  $("<i>", {
                    class: "fa-solid fa-check",
                  })
                )
              ))
            ),
            $("<div>", {
              class: "control",
            }).append(
              $("<div>", {
                class: "select",
              }).append(
                (this.stateSelect = $("<select>", {
                  id: "stateSelect",
                }))
              )
            ),
            $("<div>", {
              class: "control",
            }).append(
              (this.deleteStateBtn = $("<button>", {
                id: "deleteStateBtn",
                class: "button is-danger",
                title: this.lang.deleteState,
              }).append(
                $("<span>", {
                  class: "icon",
                }).append(
                  $("<i>", {
                    class: "fa-solid fa-trash",
                  })
                )
              ))
            )
          )
        ),
        $("<div>", {
          class: "table-container",
        }).append(
          (this.comparisonTable = $("<table>", {
            id: "comparisonTable",
            class: "table is-hoverable is-fullwidth",
          }).append(
            $("<thead>").append(
              $("<tr>").append(
                $("<th>", {
                  class: "field",
                  text: this.lang.field,
                })
              )
            ),
            $("<tbody>")
          ))
        ),
        $("<div>", {
          class: "buttons",
        }).append(
          (this.addFieldBtn = $("<button>", {
            id: "addFieldBtn",
            class: "button",
          }).append(
            $("<span>", {
              class: "icon",
            }).append(
              $("<i>", {
                class: "fa-solid fa-square-plus",
              })
            ),
            $("<span>", {
              text: this.lang.addField,
            })
          ))
        ),
        (this.addEntryModal = this.modalManager.addEntryModal),
        (this.addFieldModal = this.modalManager.addFieldModal),
        (this.previewEntryModal = this.modalManager.previewEntryModal)
      );

    this.fieldTypeSelect = this.modalManager.fieldTypeSelect;
    this.fieldSelectGroup = this.modalManager.fieldSelectGroup;
    this.fieldSelect = this.modalManager.fieldSelect;
    this.fieldFormulaGroup = this.modalManager.fieldFormulaGroup;
    this.fieldInput1Label = this.modalManager.input1Label;
    this.fieldInput1Input = this.modalManager.input1Input;
    this.fieldInput2Label = this.modalManager.input2Label;
    this.fieldInput2Input = this.modalManager.input2Input;

    this.dataSourceSelect = this.modalManager.dataSourceSelect;
    this.modelSelect = this.modalManager.modelSelect;
    this.modelSelectAllFields = this.modalManager.modelSelectAllFields;
    this.modelFieldCheckboxes = this.modalManager.modelFieldCheckboxes;
    this.entrySelect = this.modalManager.entrySelect;
    this.dataId = this.modalManager.dataId;
    this.entryLabel = this.modalManager.entryLabel;
  }

  initMutationObserver() {
    this.initSelectWatcher();
    const $thead = this.comparisonTable.find("thead");
    if (!$thead.length) return;

    this.observer = new MutationObserver((muts) => {
      const headersChanged = muts.some(
        (m) =>
          m.type === "childList" && $(m.addedNodes).add(m.removedNodes).is("th")
      );
      if (headersChanged) this.updateFieldSelect();
    });
    this.observer.observe($thead[0], {
      childList: true,
      subtree: true,
    });
  }

  initSelectWatcher() {
    const root = this.container[0];
    if (!root) return;

    this.optionsObserver = new MutationObserver((mutations) => {
      mutations.forEach((record) => {
        if (
          record.type === "childList" &&
          record.target.nodeName === "SELECT" &&
          (record.addedNodes.length || record.removedNodes.length)
        ) {
          $(record.target).trigger("optionsChanged");
        }
      });
    });
    this.optionsObserver.observe(root, {
      childList: true,
      subtree: true,
    });
  }

  /* State management methods */
  initStateManagement() {
    // TODO: Add more states as needed
    this.state = {
      editing: {
        active: false,
        currentCell: null,
      },
    };
  }

  setState(newState) {
    this.state = { ...this.state, ...newState };
  }

  setEditingState(editingState) {
    this.setState({ editing: { ...this.state.editing, ...editingState } });
  }

  getEditingState(attr = null) {
    return attr ? this.state.editing[attr] : this.state.editing;
  }
  /* End of state management methods */

  bindEvents() {
    this.bindBeforeUnloadEvent();
    this.bindAddButtonsEvents();
    this.bindModalCloseEvents();
    this.bindDataSourceChangeEvent();
    this.bindModelSelectionEvents();
    this.bindSaveActionButtons();
    this.bindFieldTypeSelectionEvents();
    this.bindEntrySelectionEvents();
    this.bindPreviewEntryEvent();
    this.bindComparisonTableEvents();
    this.bindStateManagerEvents();
    this.bindTableChangeEvents();
    this.initRowDrag();
    this.initColumnDrag();

    // Undo and redo button events
    this.undoBtn.on("click", () => this.stateManager.undo());
    this.redoBtn.on("click", () => this.stateManager.redo());
  }

  /* MutationObserver to detect table changes */

  startObserving() {
    if (this.hConfig.environment !== "production") {
      console.log("Starting MutationObserver for table changes...");
    }
    if (this.xobserver) return;

    const config = {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: [
        "data-id",
        "data-source",
        "data-model-id",
        "data-entry-id",
        "data-formula",
        "data-field-id",
      ],
    };

    this.xobserver = new MutationObserver(() => {
      if (this.hConfig.environment !== "production") {
        console.log("Handling table change via MutationObserver...");
      }
      this._handleTableChange();
    });

    const $table = this.comparisonTable;
    const thead = $table.find("thead")[0];
    const tbody = $table.find("tbody")[0];

    if (thead) this.xobserver.observe(thead, config);
    if (tbody) this.xobserver.observe(tbody, config);
  }

  stopObserving() {
    if (this.xobserver) {
      this.xobserver.disconnect();
      this.xobserver = null;
    }
  }

  _handleTableChange() {
    clearTimeout(this._debounceTimeout);
    this._debounceTimeout = setTimeout(() => {
      if (this.hConfig.environment !== "production") {
        console.log("Checking state after mutation");
      }
      this.stateManager.checkState();
    }, 300);
  }

  /* End of MutationObserver */

  // ===

  initRowDrag() {
    const tbody = this.comparisonTable.find("tbody")[0];
    if (!tbody) return;

    new Sortable(tbody, {
      handle: ".field .drag-handle", // Only draggable via drag handle within field cells
      draggable: "tr:has(td.field)", // Only rows with field cells
      animation: 150,
      onStart: (event) => {
        this.isDragging = true;
        this.stateManager.pushUndoState(); // Save the current state before dragging
      },
      onEnd: (event) => {
        this.isDragging = false;
        if (event.oldIndex !== event.newIndex) {
          // this.stateManager.markDirty(); // No need because of MutationObserver
        }
      },
    });
  }

  initColumnDrag() {
    const headerRow = this.comparisonTable.find("thead > tr")[0];
    if (!headerRow) return;

    new Sortable(headerRow, {
      handle: ".entry .drag-handle", // Only draggable via entry headers
      draggable: "th.entry", // Only entry columns
      animation: 150,
      onStart: (event) => {
        this.isDragging = true;
        this.stateManager.pushUndoState(); // Save the current state before dragging
      },
      onEnd: (event) => {
        this.isDragging = false;
        const { oldIndex, newIndex } = event;
        if (oldIndex === newIndex) return;

        // Move corresponding body cells
        this.moveBodyColumns(oldIndex, newIndex);

        // this.stateManager.markDirty();
      },
    });
  }

  moveBodyColumns(oldIndex, newIndex) {
    const tbody = this.comparisonTable.find("tbody")[0];
    if (!tbody) return;

    const rows = Array.from(tbody.rows);

    rows.forEach((row) => {
      const cells = Array.from(row.cells);

      // Get the cell to move
      const cellToMove = cells[oldIndex];
      if (!cellToMove) return;

      // Determine insert position
      let insertBefore = null;
      if (newIndex < cells.length) {
        insertBefore =
          newIndex > oldIndex ? cells[newIndex + 1] : cells[newIndex];
      }

      // Move the cell
      row.insertBefore(cellToMove, insertBefore);
    });
  }

  // ===

  // Confirm unloading if dirty
  bindBeforeUnloadEvent() {
    $(window).on("beforeunload", (e) => {
      if (this.stateManager.isDirty) {
        // trigger the native confirmation dialog
        e.preventDefault();
        e.returnValue = "";
        // for some browsers, returning a value/string is still required
        return "";
      }
    });
  }

  // Handle entry and field addition buttons
  bindAddButtonsEvents() {
    this.addEntryBtn.on("click", () => this.openEntryModal({ mode: "new" }));

    this.addFieldBtn.on("click", () => {
      if (this.comparisonTable.find("thead th").length > 1) {
        this.openFieldModal({ mode: "new" });
      } else {
        alert(this.lang.pleaseAddEntryFirst);
      }
    });
  }

  // Close modal when clicking close elements
  bindModalCloseEvents() {
    this.container
      .find(
        ".modal-card-head .delete, .modal-close, .modal-dismiss, .modal-background"
      )
      .on("click", (e) =>
        this.modalManager.closeModal($(e.currentTarget).closest(".modal"))
      );
  }

  // Toggle field entry visibility based on data source
  bindDataSourceChangeEvent() {
    this.dataSourceSelect.on("change", () => {
      const sourceId = this.dataSourceSelect.val();
      const dataSource = this.dataManager.dataSources.find(
        (d) => d.id === sourceId
      );
      $("#fieldEntrySelect").toggle(dataSource.internal);
    });
  }

  // Handle model selection and field toggling
  bindModelSelectionEvents() {
    this.modelSelect.on("change", () => this.handleModelSelection());

    this.modelSelectAllFields.on("click", () => {
      const $checkboxes = this.modelFieldCheckboxes.find(
        "input[type=checkbox]"
      );
      const allChecked =
        $checkboxes.length === $checkboxes.filter(":checked").length;
      $checkboxes.prop("checked", !allChecked);
    });
  }

  // Save actions for entries and fields
  bindSaveActionButtons() {
    $("#saveEntryBtn").on("click", () => this.saveEntry());
    $("#saveFieldBtn").on("click", () => this.saveField());
  }

  bindFieldTypeSelectionEvents() {
    this.fieldTypeSelect.on("change optionsChanged", () => {
      const fieldId = this.fieldTypeSelect.val();

      switch (fieldId) {
        case "custom-field":
        case "math-formula":
          this.fieldFormulaGroup.slideDown(200);
          this.fieldSelectGroup.slideUp(200);
          break;
        case "field":
          this.fieldSelectGroup.slideDown(200);
          this.fieldFormulaGroup.slideUp(200);
          break;
      }

      switch (fieldId) {
        case "custom-field":
          this.fieldInput1Label.text(this.lang.customFieldLabel);
          this.fieldInput1Input.attr(
            "placeholder",
            window.hyper.util.text.replacePlaceholders(this.lang.egx, {
              x: this.lang.myCustomField,
            })
          );
          this.fieldInput2Label.text(this.lang.customFieldId);
          this.fieldInput2Input.attr(
            "placeholder",
            window.hyper.util.text.replacePlaceholders(this.lang.egx, {
              x: this.lang.my_custom_field,
            })
          );
          break;
        case "math-formula":
          this.fieldInput1Label.text(this.lang.formulaLabel);
          this.fieldInput1Input.attr(
            "placeholder",
            window.hyper.util.text.replacePlaceholders(this.lang.egx, {
              x: "Field A * Field B / 100",
            })
          );
          this.fieldInput2Label.text(this.lang.formulaValue);
          this.fieldInput2Input.attr(
            "placeholder",
            window.hyper.util.text.replacePlaceholders(this.lang.egx, {
              x: "([field_a] * [field_b] / 100)",
            })
          );
          break;
        case "field":
          this.fieldInput1Input.val("");
          this.fieldInput2Input.val("");
          break;
      }
    });
  }

  // Handle entry selection changes
  bindEntrySelectionEvents() {
    this.entrySelect.on("change optionsChanged", () => {
      const previewEntryBtn = $("#previewEntry");
      previewEntryBtn.prop("disabled", !this.entrySelect.val());
      if (this.entries) {
        const entry = this.entries.find((e) => e.id === this.entrySelect.val());
        if (entry) {
          previewEntryBtn.data({
            "model-name": entry["model_name"],
            fields: entry.fields,
            "model-fields": entry.model_fields,
          });
        }
      }
    });
  }

  // Preview selected entry
  bindPreviewEntryEvent() {
    $("#previewEntry").on("click", () => this.previewEntry());
  }

  // Handle comparison table interactions
  bindComparisonTableEvents() {
    // Entry header interactions
    this.comparisonTable
      .on("mouseenter", "th.entry", (e) =>
        this.handleEntryHeaderHover(e.currentTarget)
      )
      .on("mouseleave", "th.entry", (e) =>
        this.handleEntryHeaderLeave(e.currentTarget)
      )
      .on("dblclick", "th.entry", (e) => {
        const $th = $(e.currentTarget).closest("th.entry");
        this.handleEntryHeaderClick($th);
      })
      .on("click", "th.entry a.remove-entry", (e) => {
        e.stopPropagation();
        this.removeEntry(e);
      });

    // Field cell interactions
    this.comparisonTable
      .on("mouseenter", "td.field", (e) =>
        this.handleFieldCellHover(e.currentTarget)
      )
      .on("mouseleave", "td.field", (e) =>
        this.handleFieldCellLeave(e.currentTarget)
      )
      .on("dblclick", "td.field", (e) => {
        const $td = $(e.currentTarget); //.closest("td.field");
        this.handleFieldCellDoubleClick($td);
      })
      .on("click", "td.field a.remove-field", (e) => {
        e.stopPropagation();
        this.removeField(e);
      });

    // Cell editing
    this.comparisonTable.on("dblclick", "tr td:not([data-field-id])", (e) => {
      if (this.getEditingState("active")) {
        // Show message
        window.hyper.factory.swal.error(this.lang.pleaseFinishCurrentEditFirst);
        return;
      }

      const $clickedTd = $(e.currentTarget);
      const $firstTd = $clickedTd.closest("tr").find("td:first-child");
      const fieldType = $firstTd.data("field-type");

      if (fieldType !== "math-formula") {
        this.setEditingState({ active: true, currentCell: $clickedTd });
        this.editCell(e);
      }
    });
  }

  // Entry header hover effect
  handleEntryHeaderHover(th) {
    if (this.isDragging) {
      this.handleEntryHeaderLeave();
      return;
    }

    const $th = $(th);
    if ($th.find(".remove-entry").length) return;

    // Create a remove button
    $("<a>", {
      class: "tag remove-entry is-danger mr-2",
      title: this.langAdmin.delete,
    })
      .append($("<i>", { class: "fas fa-xmark" }))
      .hide()
      .prependTo($th)
      .show(200);

    // Create a drag handle
    $("<span>", {
      class: "drag-handle icon is-small mr-2",
      title: this.lang.dragToReorderEntry,
    })
      .append($("<i>", { class: "fas fa-grip-lines-vertical" }))
      .hide()
      .prependTo($th)
      .show(200);
  }

  // Entry header leave effect
  handleEntryHeaderLeave(th) {
    // Remove remove button
    $(th)
      .find(".remove-entry")
      .hide(200, function () {
        $(this).remove();
      });

    // Remove drag handle
    $(th)
      .find(".drag-handle")
      .hide(200, function () {
        $(this).remove();
      });
  }

  // Entry header click handler
  handleEntryHeaderClick($th) {
    const $current = $th;
    const dataSource = $current.attr("data-source");
    const modelId = $current.attr("data-model-id");
    const entryId = $current.attr("data-entry-id");
    const dataId = $current.attr("data-id");

    const entryLabel = $current.clone().children().remove().end().text().trim();

    this.openEntryModal({
      mode: "edit",
      id: dataId,
    });

    this.entrySelect.empty().append(
      $("<option>", {
        val: entryId,
        text: entryLabel,
      })
    );

    this.dataSourceSelect.val(dataSource);
    this.entrySelect.val(entryId);
    this.modelSelect.val(modelId);

    this.dataSourceSelect.trigger("change");
    this.modelSelect.trigger("change");
    this.entrySelect.trigger("change");

    this.dataId.val(dataId);
    this.entryLabel.val(entryLabel);
  }

  handleFieldCellDoubleClick($td) {
    const fieldId = $td.attr("data-field-id");
    if (this.hConfig.environment !== "production") {
      console.log(fieldId);
    }
    this.openFieldModal({ mode: "edit", fieldId: fieldId });
  }

  // Field cell hover effect
  handleFieldCellHover(td) {
    if (this.isDragging) {
      this.handleFieldCellLeave();
      return;
    }

    const $td = $(td);
    if ($td.find(".remove-field").length) return;

    // Create a remove button
    $("<a>", {
      class: "tag remove-field is-danger mr-2",
      title: this.langAdmin.delete,
    })
      .append($("<i>", { class: "fas fa-xmark" }))
      .hide()
      .prependTo($td)
      .show(200);

    // Create a drag handle
    $("<span>", {
      class: "drag-handle icon is-small mr-2",
      title: this.lang.dragToReorderField,
    })
      .append($("<i>", { class: "fas fa-grip-lines" }))
      .hide()
      .prependTo($td)
      .show(200);
  }

  // Field cell leave effect
  handleFieldCellLeave(td) {
    // Remove remove button
    $(td)
      .find(".remove-field")
      .hide(200, function () {
        $(this).remove();
      });

    // Remove drag handle
    $(td)
      .find(".drag-handle")
      .hide(200, function () {
        $(this).remove();
      });
  }

  // State management actions
  bindStateManagerEvents() {
    this.saveBtn.on("click", () => this.stateManager.saveTableState());
    this.saveAsBtn.on("click", () => this.stateManager.saveAsTableState());
    this.loadStateBtn.on("click", () => this.stateManager.loadSelectedState());
    this.deleteStateBtn.on("click", () =>
      this.stateManager.deleteSelectedState()
    );
  }

  // Mark state as dirty when table headers change
  bindTableChangeEvents() {
    this.comparisonTable.on(
      "change",
      "thead th, tbody tr td:first-child",
      () => {
        // this.stateManager.markDirty()
      }
    );
  }

  handleModelSelection() {
    const mode = $(this.addEntryModal).attr("data-mode");
    const modelId = this.modelSelect.val();
    const oldEntryId = $(this.entrySelect).val();

    if (this.hConfig.environment !== "production") {
      console.log(
        "Handling model selection...",
        "Mode:",
        mode,
        "Model ID:",
        modelId,
        "Old entry ID:",
        oldEntryId
      );
    }

    this.handleModelSelectionUi();

    if (mode === "edit" && oldEntryId) {
      if (this.hConfig.environment !== "production") {
        console.log("Populate entry select for editing:", oldEntryId);
      }
      this.dataManager.populateEntrySelect(modelId, oldEntryId);
    } else {
      this.dataManager.populateEntrySelect(modelId);
    }
  }

  handleModelSelectionUi() {
    const modelId = this.modelSelect.val();
    this.dataManager.populateFieldCheckboxes(modelId);
    this.modelSelectAllFields.toggle(
      this.modelFieldCheckboxes.children().length > 0
    );
  }

  previewEntry() {
    const $btn = $("#previewEntry");
    const modelName = $btn.data("model-name");
    let fields = $btn.data("fields");
    try {
      fields = typeof fields === "string" ? JSON.parse(fields) : fields;
    } catch (e) {
      console.error("Error parsing fields:", e);
      $("#previewEntryContent").text("Error loading fields");
      return;
    }

    const $container = $("#previewEntryContent").empty();
    $container.append(
      $("<table>", {
        class: "table is-fullwidth is-striped",
      }).append(
        $("<thead>").append(
          $("<tr>").append(
            $("<th>", {
              text: this.lang.fieldId,
            }),
            $("<th>", {
              text: this.lang.value,
            })
          )
        ),
        $("<tbody>").append(
          fields.map((f) =>
            $("<tr>").append(
              $("<td>", {
                text: f.id,
              }),
              $("<td>", {
                text: f.value,
              })
            )
          )
        )
      )
    );
    this.modalManager.openModal(this.previewEntryModal);
  }

  saveEntry() {
    const mode = this.addEntryModal.attr("data-mode");
    const oldEntryId = $(this.addEntryModal).attr("data-id");
    const sourceId = this.dataSourceSelect.val();
    const source = this.dataManager.dataSources.find(
      (src) => src.id === sourceId
    );
    const modelId = this.modelSelect.val();
    const entryId = source.internal ? this.entrySelect.val() : null; // Clear entryId if source is external
    const dataId = this.dataId.val();
    const label =
      this.entryLabel.val() || this.entrySelect.find("option:selected").text();
    const selectedFields = this.modelFieldCheckboxes.find("input:checked");

    if (!modelId || !(source.internal ? entryId : dataId)) {
      window.hyper.factory.swal.error(this.lang.pleaseFillAllRequiredFields);
      return;
    }

    const finalId = dataId || `e${entryId}`;
    if (this.hConfig.environment !== "production") {
      console.log(
        "Updating entry...",
        "Mode:",
        mode,
        "Old entry ID:",
        oldEntryId,
        "Data ID:",
        finalId
      );
    }

    if (mode === "edit") {
      // If the entry selected is different than the old entry,
      // make sure the selected is not yet added to the table
      if (
        finalId !== oldEntryId &&
        this.comparisonTable.find(`th[data-id="${finalId}"]`).length
      ) {
        window.hyper.factory.swal.error(
          this.lang.columnWithSameIdAlreadyExists
        );
        return;
      }
      this.stateManager.pushUndoState(); // Save the current state before making changes
      this.editEntryColumn(
        oldEntryId,
        sourceId,
        modelId,
        entryId,
        finalId,
        label
      );
    } else if (mode === "new") {
      if (this.comparisonTable.find(`th[data-id="${finalId}"]`).length) {
        window.hyper.factory.swal.error(
          this.lang.columnWithSameIdAlreadyExists
        );
        return;
      }
      this.stateManager.pushUndoState(); // Save the current state before making changes
      this.addEntryColumn(sourceId, modelId, entryId, finalId, label);
    }

    selectedFields.each((_, checkbox) => {
      this.addFieldRow({
        fieldId: checkbox.value,
        title: $(checkbox).siblings("span").text().trim(),
      });
    });

    this.modalManager.closeModal(this.addEntryModal);
    this.dataManager.fetchCellData();
  }

  /**
   * Handles saving a field in the comparison table, either by adding a new field or editing an existing one.
   * Supports both standard fields and custom math formula fields.
   * - For math formula fields, generates a unique field ID using the encoded formula and current timestamp.
   * - Validates required inputs and displays alerts if necessary.
   * - Pushes the current state to the undo stack before making changes.
   * - Updates the table by adding or editing the field row, closes the modal, and refreshes cell data or reevaluates formulas.
   *
   * @returns {void}
   */
  saveField() {
    const mode = this.addFieldModal.attr("data-mode");
    const oldFieldId = this.addFieldModal.attr("data-field-id");
    const fieldType = this.fieldTypeSelect.val();
    if (!fieldType) {
      alert(this.lang.pleaseSelectFieldType);
      return;
    }
    let fieldId = this.fieldSelect.val();
    let input1,
      input2 = null;
    let additionalAttrs = {}; // Placeholder for any additional attributes

    if (this.hConfig.environment !== "production") {
      console.log(
        "Updating field...",
        "Mode:",
        mode,
        "Old field ID:",
        oldFieldId,
        "Data ID:",
        fieldId
      );
    }

    if (fieldType === "math-formula" || fieldType === "custom-field") {
      input1 = this.fieldInput1Input.val().trim();
      input2 = this.fieldInput2Input.val().trim();

      if (fieldType === "custom-field") {
        /* CUSTOM FIELD */
        const customFieldLabel = input1; // The field label
        const customFieldId = input2; // The field ID

        if (!customFieldId) {
          alert(this.lang.pleaseProvideBothFieldLabelAndId);
          return;
        }

        // Overwrite fieldId with the user-provided custom field ID
        fieldId = customFieldId;
        // customFieldId = null; // Custom fields do not have a formula value

        // additionalAttrs = { "data-custom-field": "true" }; // Mark as custom field
      } else if (fieldType === "math-formula") {
        /* MATH FORMULA FIELD */
        const formulaLabel = input1; // The field label
        const formulaValue = input2; // The field value is the formula itself

        if (!formulaValue) {
          alert(this.lang.pleaseProvideFormula);
          return;
        }

        // Overwrite fieldId with a unique identifier using the encoded formula and current timestamp.
        fieldId = `math-formula-${
          window.hyper.util.hex.encode(formulaValue) + "-" + Date.now()
        }`;

        additionalAttrs = { "data-formula": formulaValue }; // Store the formula as a data attribute
      }
    } else if (fieldType === "field") {
      input1 = this.fieldSelect.find("option:selected").text();

      if (!fieldId) {
        alert(this.lang.pleaseSelectField);
        return;
      }
    }

    if (mode === "edit") {
      this.stateManager.pushUndoState(); // Save the current state before making changes
      this.editFieldRow({
        fieldType: fieldType,
        oldFieldId: oldFieldId,
        newFieldId: fieldId,
        title: input1,
        tag: input2,
        additionalAttrs: additionalAttrs,
      });
    } else {
      this.stateManager.pushUndoState(); // Save the current state before making changes
      this.addFieldRow({
        fieldType: fieldType,
        fieldId: fieldId,
        title: input1,
        tag: input2,
        additionalAttrs: additionalAttrs,
      });
    }

    this.modalManager.closeModal(this.addFieldModal);

    // If the field type is math formula, reevaluate all formula cells; otherwise, fetch data for standard fields.
    if (fieldType === "math-formula") {
      this.reevaluateFormulaCells();
    } else {
      this.dataManager.fetchCellData();
    }
  }

  addEntryColumn(sourceId, modelId, entryId, dataId, label) {
    const $header = $("<th>", {
      class: "entry",
      "data-id": dataId,
      "data-source": sourceId,
      "data-model-id": modelId,
      "data-entry-id": entryId,
      text: label,
      append: [
        $("<span>", {
          class: "tag",
          text: `(${dataId})`,
        }),
      ],
    });

    this.comparisonTable.find("thead tr").append($header);
    this.comparisonTable.find("tbody tr").each((_, row) => {
      $(row).append($("<td>").append($("<pre>").text("N/A")));
    });
    // this.stateManager.markDirty();
  }

  editEntryColumn(oldEntryId, sourceId, modelId, entryId, dataId, label) {
    // 1) locate the existing <th> by its entry-id
    const $oldTh = this.comparisonTable.find(
      `thead th[data-id="${oldEntryId}"]`
    );

    if (!$oldTh.length) {
      console.warn(
        `editEntryColumn: no column found for entryId="${oldEntryId}"`
      );
      return;
    }

    // 2) build a fresh <th> with the new attributes & label
    const $newTh = $("<th>", {
      class: "entry",
      "data-id": dataId,
      "data-source": sourceId,
      "data-model-id": modelId,
      "data-entry-id": entryId,
      text: label,
      append: [
        $("<span>", {
          class: "tag",
          text: `(${dataId})`,
        }),
      ],
    });

    // 3) swap the old header for the new one in the THEAD
    $oldTh.replaceWith($newTh);

    // 4) mark the table state as modified
    // this.stateManager.markDirty();
  }

  enableEntryLoading(entryId) {
    if (this.hConfig.environment !== "production") {
      console.log("Enabling loading state for entry:", entryId);
    }

    const $header = this.findCellHeader(entryId);
    if (!$header) return;

    if ($header.find(".entry-loading").length > 0) return;

    $header.append(
      $("<button>", {
        class: "entry-loading button is-small is-loading",
        text: "...",
      })
    );
  }

  disableEntryLoading(entryId) {
    const $header = this.findCellHeader(entryId);
    if (!$header) return;

    $header.find(".entry-loading").remove();
  }

  addFieldRow({
    fieldType = "field",
    fieldId,
    title, // Main label
    tag = null, // Tag label
    position = null,
    additionalAttrs = {},
  }) {
    if (!tag && this.fieldIsExist(fieldId)) {
      return window.hyper.factory.swal.error(`Field ${title} already exists`);
    }

    const $row = $("<tr>").append(
      $("<td>", {
        class: "field",
        "data-field-id": fieldId,
        "data-field-type": fieldType,
        text: title,
        ...additionalAttrs,
      }).append(
        $("<span>", {
          class: "tag",
          text: `(${tag || fieldId})`,
        })
      )
    );

    const colCount = this.comparisonTable.find("thead th").length - 1;
    for (let i = 0; i < colCount; i++) {
      $row.append(
        $("<td>").append(
          $("<pre>", {
            text: this.langAdmin["n/a"],
          })
        )
      );
    }

    const $tbody = this.comparisonTable.find("tbody");
    // If position is provided and valid, insert at that index; otherwise, append.
    if (
      position !== null &&
      position >= 0 &&
      position < $tbody.find("tr").length
    ) {
      $tbody.find("tr").eq(position).before($row);
    } else {
      $tbody.append($row);
    }

    // this.stateManager.markDirty();
  }

  editFieldRow({
    fieldType,
    oldFieldId,
    newFieldId,
    title, // Main label
    tag = null, // Tag label
    additionalAttrs = {},
  }) {
    // Locate the existing row using the old field's ID.
    const $oldRow = this.comparisonTable
      .find(`tbody td.field[data-field-id="${oldFieldId}"]`)
      .closest("tr");
    const $newRow = this.comparisonTable
      .find(`tbody td.field[data-field-id="${newFieldId}"]`)
      .closest("tr");

    if (!$oldRow.length) {
      console.warn(`editFieldRow: No row found for field id "${oldFieldId}"`);
      return window.hyper.factory.swal.error(
        window.hyper.util.text.replacePlaceholders(
          this.lang.fieldxDoesNotExist,
          { x: title }
        )
      );
    }
    if ($newRow.length) {
      console.warn(
        `editFieldRow: Field already exists for field id "${newFieldId}"`
      );
      return window.hyper.factory.swal.error(
        window.hyper.util.text.replacePlaceholders(
          this.lang.fieldxAlreadyExists,
          { x: title }
        )
      );
    }

    if (this.hConfig.environment !== "production") {
      console.log(oldFieldId, $oldRow);
    }

    // Capture the position of the old row before removing it.
    const pos = $oldRow.index();

    // Remove the old field row.
    $oldRow.remove();

    // Insert the updated field row at the same position using addFieldRow.
    this.addFieldRow({
      fieldType: fieldType,
      fieldId: newFieldId,
      title: title,
      tag: tag,
      position: pos,
      additionalAttrs: additionalAttrs,
    });
  }

  /**
   * Updates the field selection dropdown based on the current table headers.
   * If no headers are present, resets the dropdown to a default option.
   * Otherwise, finds the first header with a model ID and populates the dropdown
   * with fields from the corresponding model using the data manager.
   */
  updateFieldSelect() {
    const $headers = this.comparisonTable.find("thead th:not(:first)");
    if ($headers.length === 0) {
      this.fieldSelect
        .empty()
        .append($("<option>").val("").text(this.lang.chooseField));
      return;
    }

    let modelId = null;
    for (let i = 0; i < $headers.length; i++) {
      modelId = $headers.eq(i).data("model-id");
      if (modelId) break;
    }

    if (modelId) this.dataManager.populateFieldSelect(modelId);
  }

  openEntryModal({ mode, id, data }) {
    this.modalManager.openEntryModal({ mode, id, data });
    this.handleModelSelectionUi();
  }

  openFieldModal({ mode, fieldId }) {
    this.modalManager.openFieldModal({ mode, fieldId });

    const field = this.getFieldById(fieldId);
    const fieldType = field ? field.data("field-type") : "field";
    const fieldIdValue = field ? field.data("field-id") : "";
    const fieldLabel = field
      ? field.clone().children().remove().end().text().trim()
      : "";
    const fieldName = field ? field.find(".tag").text().trim() : "";

    let input1 = "";
    let input2 = "";

    if (fieldType === "custom-field") {
      input1 = fieldLabel;
      input2 = fieldIdValue;
    } else if (fieldType === "math-formula") {
      input1 = fieldLabel;
      input2 = field ? field.data("formula") || "" : "";
    }

    this.fieldTypeSelect.val(fieldType).trigger("change");
    this.fieldSelect.val(fieldIdValue).trigger("change");
    this.fieldInput1Input.val(input1);
    this.fieldInput2Input.val(input2);
  }

  getFieldById(fieldId) {
    return this.comparisonTable
      .find(`tbody td.field[data-field-id="${fieldId}"]`)
      .first();
  }

  /**
   * Reevaluates all formula cells in the comparison table.
   * For each row, retrieves the base formula from the first cell,
   * then iterates over the remaining cells to evaluate their formulas.
   * Injects cell values into formulas, evaluates the expressions,
   * and updates the cell text with the result or an error message.
   * Handles errors gracefully and logs them to the console.
   */
  reevaluateFormulaCells() {
    this.comparisonTable.find("tbody tr").each((_, row) => {
      const $row = $(row);
      const baseFormula = $row.find("td:first").data("formula");
      $row.find("td:not(:first)").each((_, cell) => {
        const $cell = $(cell);
        const cellFormula = $cell.data("formula") || baseFormula;
        if (!cellFormula) return;

        const colIndex = $cell.index();
        const entryId = this.comparisonTable
          .find(`thead th:eq(${colIndex})`)
          .data("entry-id");
        try {
          const expression = this.injectCellValues(cellFormula, entryId);
          const result = math.evaluate(expression);
          $cell.text(isNaN(result) ? "Error" : result);
        } catch (e) {
          console.error("Error evaluating formula:", e);
          $cell.text(this.langAdmin.error);
        }
      });
    });
  }

  findCell(entryId, fieldId) {
    let $header = this.findCellHeader(entryId);
    if (!$header) return null;

    const colIndex = $header.index();
    const $row = this.comparisonTable.find(
      `tbody tr:has(td[data-field-id="${fieldId}"])`
    );
    return $row.length ? $row.find(`td:eq(${colIndex})`) : null;
  }

  findCellHeader(entryId) {
    let $header = this.comparisonTable.find(`th[data-id="${entryId}"]`);
    if ($header.length === 0)
      $header = this.comparisonTable.find(`th[data-entry-id="${entryId}"]`);
    if ($header.length === 0) return null;
    return $header;
  }

  removeEntry(e) {
    const $th = $(e.currentTarget).closest("th");
    const index = $th.index();

    this.stateManager.pushUndoState(); // Save the current state before making changes
    $th.remove();
    this.comparisonTable.find("tbody tr").each((_, row) => {
      $(row).find("td").eq(index).remove();
    });
  }

  removeField(e) {
    this.stateManager.pushUndoState(); // Save the current state before making changes

    $(e.currentTarget).closest("tr").remove();
    this.reevaluateFormulaCells(); // Re-evaluate formula cells
  }

  /**
   * Enables inline editing of a table cell by replacing its content with a textarea.
   * When the textarea loses focus, updates the cell's text and triggers a cell update handler if the value changed.
   *
   * @param {jQuery.Event} e - The event object triggered by clicking the table cell.
   */
  editCell(e) {
    const $td = $(e.currentTarget);
    const originalText = $td.text().trim();
    const $textarea = $("<textarea>", {
      class: "textarea",
    })
      .val(originalText)
      .css({
        width: $td.innerWidth() + "px",
        height: $td.innerHeight() + "px",
        margin: 0,
        border: "none",
      });

    $td.empty().append($textarea);
    requestAnimationFrame(() => $textarea.focus().select());

    $textarea.on("blur", () => {
      // Exit edit mode
      this.setEditingState({ active: false, currentCell: null });

      const newText = $textarea.val().trim();
      $td.text(newText);
      if (newText !== originalText)
        this.handleCellUpdate($td, originalText, newText);
    });
  }

  handleCellUpdate($td, originalText, newText) {
    const colIndex = $td.index();
    const $headerCell = this.comparisonTable.find("thead th").eq(colIndex);
    const sourceId = $headerCell.data("source");
    const source = this.dataManager.dataSources.find((d) => d.id === sourceId);

    if (!source.internal) {
      $td.text(originalText);
      window.hyper.factory.swal.error(this.lang.entryFromExternalDataSource);
      return;
    }

    const modelId = $headerCell.data("model-id");
    const entryId = $headerCell.data("entry-id");
    const $row = $td.closest("tr");
    const fieldType = $row.find("td:first").data("field-type");
    const fieldId = $row.find("td:first").data("field-id");
    const isCustomField = fieldType !== "custom-field";

    if (fieldId && isCustomField) {
      $.ajax({
        url: `${this.hConfig.baseUrl}admin/data-comparison/update`,
        method: "POST",
        data: {
          [this.hConfig.csrfToken]: this.hConfig.csrfHash,
          modelId: modelId,
          entryId: entryId,
          fields: [
            {
              id: fieldId,
              value: newText,
            },
          ],
        },
        success: () => {
          window.hyper.factory.swal.success(window.hyper.lang.Admin.success);
          this.reevaluateFormulaCells();
        },
        error: (xhr) => {
          window.hyper.factory.swal.error(
            xhr.responseJSON.error ?? window.hyper.lang.Admin.error,
            {
              text: xhr.responseJSON.message,
            }
          );
          $td.text(originalText);
        },
      });
    } else if (isCustomField) {
      // For custom fields, just reevaluate formulas
      this.reevaluateFormulaCells();
    }
  }

  injectCellValues(formula, currentEntryId) {
    return formula.replace(/\[([^\[\]]+)\]/g, (match, token) => {
      const parts = token.split(":");
      let modelId = null,
        entryId = currentEntryId,
        dataId = null,
        fieldName;

      if (parts.length === 3) {
        modelId = parts[0].replace(/^m/i, "");
        entryId = parts[1].replace(/^e/i, "");
        fieldName = parts[2];
      } else if (parts.length === 2) {
        fieldName = parts[1];
        const prefix = parts[0];
        if (/^e\d+$/i.test(prefix)) entryId = prefix.replace(/^e/i, "");
        else if (/^m\d+$/i.test(prefix)) modelId = prefix.replace(/^m/i, "");
        else dataId = prefix;
      } else {
        fieldName = parts[0];
      }

      let value = 0;
      if (dataId) {
        const $cell = this.getComparisonCellByDataIdAndFieldId(
          dataId,
          fieldName
        );
        value = $cell ? parseFloat($cell.text().trim()) || 0 : 0;
      } else {
        const $cell = this.getComparisonCell(entryId, fieldName, modelId);
        value = $cell ? parseFloat($cell.text().trim()) || 0 : 0;
      }
      return value;
    });
  }

  getComparisonCellByDataIdAndFieldId(dataId, fieldId) {
    const $header = this.comparisonTable.find(`th[data-id="${dataId}"]`);
    if ($header.length === 0) return null;
    const colIndex = $header.index();
    const $row = this.comparisonTable.find(
      `tbody tr:has(td[data-field-id="${fieldId}"])`
    );
    return $row.length ? $row.find(`td:eq(${colIndex})`) : null;
  }

  getComparisonCell(entryId, fieldId, modelId = null) {
    let selector = `th[data-entry-id="${entryId}"]`;
    if (modelId)
      selector = `th[data-model-id="${modelId}"][data-entry-id="${entryId}"]`;
    const $header = this.comparisonTable.find(`thead ${selector}`);
    if ($header.length === 0) return null;
    const colIndex = $header.index();
    const $row = this.comparisonTable.find(
      `tbody tr:has(td[data-field-id="${fieldId}"])`
    );
    return $row.length ? $row.find(`td:eq(${colIndex})`) : null;
  }

  showError(message) {
    console.error(message);
    window.hyper.factory.swal.error(message);
  }

  fieldIsExist(fieldId) {
    return (
      this.comparisonTable.find(`td[data-field-id="${fieldId}"]`).length > 0
    );
  }
}
