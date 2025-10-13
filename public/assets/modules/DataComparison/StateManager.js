import $ from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";

export class StateManager {
  constructor(comparisonTable) {
    this.comparisonTable = comparisonTable;

    // Import language
    this.lang = comparisonTable.lang;
    this.langAdmin = comparisonTable.langAdmin;

    this.savedStates = {};
    this.currentStateName = null;
    this.isDirty = false;
    this.undoStack = []; // Track undoable states
    this.redoStack = []; // Track redoable states

    // Capture initial state
    this.pushUndoState();
  }

  checkState() {
    let currentState, comparisonState;

    if (this.currentStateName) {
      // Compare with last saved state
      currentState = this._captureState();
      comparisonState = this.savedStates[this.currentStateName];

      if (this.comparisonTable.hConfig.environment !== "production") {
        console.log("Comparing with saved state:", this.currentStateName);
      }
    } else if (this.undoStack.length > 0) {
      // Compare with initial state
      currentState = this._captureState();
      comparisonState = this.undoStack[0];

      if (this.comparisonTable.hConfig.environment !== "production") {
        console.log("Comparing with initial state");
      }
    } else {
      // No state to compare against
      this.markClean();

      if (this.comparisonTable.hConfig.environment !== "production") {
        console.log("No state to compare against");
      }
      return;
    }

    // Normalize states for comparison
    const normalize = (state) => {
      const { timestamp, ...rest } = state;
      return JSON.stringify(rest);
    };

    const isClean = normalize(currentState) === normalize(comparisonState);

    if (this.comparisonTable.hConfig.environment !== "production") {
      console.log("State comparison:", {
        isClean,
        currentState,
        comparisonState,
      });
    }

    isClean ? this.markClean() : this.markDirty();
  }

  markDirty() {
    this.isDirty = true;
    this.comparisonTable.saveBtn.addClass("is-warning");
  }

  markClean() {
    this.isDirty = false;
    this.comparisonTable.saveBtn.removeClass("is-warning");
  }

  saveAsTableState() {
    const stateName = prompt(this.lang.enterNameForState);
    if (!stateName) return;

    if (
      this.savedStates[stateName] &&
      !confirm(
        window.hyper.util.text.replacePlaceholders(
          this.lang.statexAlreadyExistsOverwrite,
          { x: stateName }
        )
      )
    ) {
      return;
    }

    this.saveTableState(stateName);
  }

  saveTableState(stateName = this.currentStateName) {
    if (!stateName) return this.saveAsTableState();

    const state = this._captureState();

    // Save to states collection
    this.savedStates[stateName] = state;
    this.currentStateName = stateName;
    this.saveStatesToStorage();

    this.markClean();

    window.hyper.factory.swal.success(
      window.hyper.util.text.replacePlaceholders(
        this.lang.statexSavedSuccessfully,
        { x: stateName }
      )
    );
    this.populateStateSelect();
  }

  loadSelectedState() {
    const stateName = this.comparisonTable.stateSelect.val();
    if (!stateName) return;

    if (this.isDirty && !confirm(this.lang.youHaveUnsavedChangesLoadAnyway)) {
      return;
    }

    this.loadTableState(stateName);
  }

  /**
   * Loads and applies a saved table state by name.
   * If no state name is provided, attempts to load the last used state from localStorage.
   * Applies the state to the table, updates the current state name, marks the state as clean,
   * and updates the state selection UI.
   *
   * @param {string|null} [stateName=null] - The name of the state to load. If null, loads the last state.
   * @returns {boolean} True if the state was successfully loaded and applied, false otherwise.
   */
  loadTableState(stateName = null) {
    if (!stateName) {
      return this.loadLastUsedState();
    }

    const state = this.savedStates[stateName];
    if (!state) return false;

    this._applyState(state); // Apply the saved state to the table

    this.currentStateName = stateName;
    this.saveLastUsedState();

    this.markClean();

    // Update state select UI
    this.comparisonTable.stateSelect.val(stateName);

    return true;
  }

  loadLastUsedState({
    onSuccess = () => {},
    onError = () => {},
    onComplete = () => {},
  }) {
    // Load state from API, fallback to localStorage
    $.ajax({
      url: `${this.comparisonTable.baseUrl}admin/settings/data-comparison/get-last-used-state`,
      type: "GET",
      success: (response) => {
        if (this.comparisonTable.hConfig.environment !== "production") {
          console.log("Response from get-last-used-state API:", response);
        }

        if (response.success && response.success.trim() !== "") {
          this.loadTableState(response.success);
          onSuccess();
          onComplete();
          return true;
        } else {
          onError();
          onComplete();
          return this.loadLastUsedStateFromLocal();
        }
      },
      error: (_xhr, _status, error) => {
        console.error("Failed to load last used state from server:", error);

        // Show error message
        window.hyper.factory.swal.error(this.lang.failedToFetchLastUsedState);

        onError(error);
        onComplete();
        return this.loadLastUsedStateFromLocal();
      },
    });
  }

  loadLastUsedStateFromLocal() {
    const lastState = localStorage.getItem("comparisonTableLastState");
    if (lastState) {
      return this.loadTableState(lastState);
    }
    return false;
  }

  saveLastUsedState() {
    let stateName = this.currentStateName;

    if (!stateName) return;

    // Save to localStorage
    localStorage.setItem("comparisonTableLastState", stateName);

    // Also save to API
    $.ajax({
      url: `${this.comparisonTable.baseUrl}admin/settings/data-comparison/save-last-used-state`,
      type: "POST",
      headers: {
        [window.hyper.config.csrfHeader]: window.hyper.config.csrfHash,
      },
      data: {
        state: stateName,
      },
      success: (response) => {
        if (this.comparisonTable.hConfig.environment !== "production") {
          console.log("Saved last used state to server:", response);
        }
      },
      error: (xhr, status, error) => {
        console.error("Failed to save last used state to server:", error);

        // Show error message
        window.hyper.factory.swal.error(this.lang.failedToSaveLastUsedState);
      },
    });
  }

  deleteSelectedState() {
    const stateName = this.comparisonTable.stateSelect.val();
    if (!stateName) return;

    if (
      !confirm(
        window.hyper.util.text.replacePlaceholders(this.lang.deleteStatex, {
          x: stateName,
        })
      )
    )
      return;

    delete this.savedStates[stateName];
    this.saveStatesToStorage();

    if (this.currentStateName === stateName) {
      this.currentStateName = null;
    }

    this.populateStateSelect();
    window.hyper.factory.swal.success(
      window.hyper.util.text.replacePlaceholders(
        this.lang.statexDeletedSuccessfully,
        { x: stateName }
      )
    );
  }

  loadStateListFromSettings({
    onSuccess = () => {},
    onError = () => {},
    onComplete = () => {},
  }) {
    // Get saved states from settings API endpoint using AJAX and fallback to localStorage
    $.ajax({
      url: `${this.comparisonTable.baseUrl}admin/settings/data-comparison/load-state`,
      type: "GET",
      success: (response) => {
        if (this.comparisonTable.hConfig.environment !== "production") {
          console.log("Response from load-state API:", response);
        }

        // If response.success is missing or empty, fallback to localStorage
        let parsedStates = {};
        let parseFailed = false;
        if (!response.success || response.success.trim() === "") {
          parseFailed = true;
        } else {
          try {
            parsedStates = JSON.parse(response.success);

            // If parsedStates is not an object or is empty
            if (Object.keys(parsedStates).length === 0) {
              parseFailed = true;
            }
          } catch (e) {
            console.error("Failed to parse states JSON from server:", e);
            parseFailed = true;
          }
        }
        if (parseFailed) {
          if (this.comparisonTable.hConfig.environment !== "production") {
            console.error("Falling back to localStorage for saved states.");
          }

          this.loadStateList();
          onSuccess(this.savedStates);
          onComplete();
          return;
        }

        // Parse the JSON string inside response.success
        try {
          this.savedStates = JSON.parse(response.success);
        } catch (e) {
          console.error("Failed to parse states JSON from server:", e);
          this.savedStates = {};
        }

        if (this.comparisonTable.hConfig.environment !== "production") {
          console.log("Loaded saved states from server:", this.savedStates);
        }
        this.populateStateSelect();
        onSuccess(this.savedStates);
        onComplete();
      },
      error: (xhr, status, error) => {
        console.error("Failed to load states from server:", error);

        // Show error message
        window.hyper.factory.swal.error(this.lang.failedToFetchStates);

        this.loadStateList(); // Fallback to localStorage

        onError(error);
        onComplete();
      },
    });
  }

  loadStateList() {
    const saved = localStorage.getItem("comparisonTableStates");
    if (this.comparisonTable.hConfig.environment !== "production") {
      console.log("Loaded saved states:", saved);
    }
    if (saved) {
      this.savedStates = JSON.parse(saved);
    }
    this.populateStateSelect();
  }

  populateStateSelect() {
    this.comparisonTable.stateSelect.empty().append(
      $("<option>", {
        value: "",
        text: this.lang.selectState,
        disabled: true,
        selected: true,
      })
    );

    Object.keys(this.savedStates)
      .sort()
      .forEach((name) => {
        const state = this.savedStates[name];
        const option = $("<option>", {
          value: name,
          text: name,
        });

        if (state.timestamp) {
          option.attr("title", new Date(state.timestamp).toLocaleString());
        }

        this.comparisonTable.stateSelect.append(option);
      });

    if (this.currentStateName) {
      this.comparisonTable.stateSelect.val(this.currentStateName);
    }
  }

  saveStatesToStorage() {
    // Save to settings API endpoint using AJAX
    if (!window.hyper.config.csrfHeader || !window.hyper.config.csrfHash) {
      console.warn("CSRF header or token is missing!");
    }
    $.ajax({
      url: `${this.comparisonTable.baseUrl}admin/settings/data-comparison/save-state`,
      type: "POST",
      headers: {
        [window.hyper.config.csrfHeader]: window.hyper.config.csrfHash,
      },
      data: {
        states: JSON.stringify(this.savedStates),
      },
      success: (response) => {
        if (this.comparisonTable.hConfig.environment !== "production") {
          console.log("Saved states to server:", response);
        }

        // Show success message
        window.hyper.factory.swal.success(this.lang.statesSavedSuccessfully);
      },
      error: (xhr, status, error) => {
        console.error("Failed to save states to server:", error);

        // Show error message
        window.hyper.factory.swal.error(this.lang.failedToSaveStates);
      },
    });

    // Also save to localStorage as fallback
    localStorage.setItem(
      "comparisonTableStates",
      JSON.stringify(this.savedStates)
    );

    // Save last loaded state name
    this.saveLastUsedState();
  }

  // New methods for undo/redo functionality
  _captureState() {
    const state = {
      headers: [],
      fields: [],
      cells: [],
      timestamp: new Date().toISOString(),
    };

    // Capture headers
    this.comparisonTable.comparisonTable
      .find("thead th:not(:first)")
      .each((_, th) => {
        const $th = $(th);
        state.headers.push({
          id: String($th.data("id")),
          source: String($th.data("source")),
          modelId: String($th.data("model-id")),
          entryId: String($th.data("entry-id")),
          label: $th.clone().children().remove().end().text().trim(),
        });
      });

    // Capture fields
    this.comparisonTable.comparisonTable.find("tbody tr").each((_, tr) => {
      const $tr = $(tr);
      const $firstTd = $tr.find("td:first");
      const fieldType = $firstTd.data("field-type");
      const isCustomField = $firstTd.is("[data-custom-field]"); // Check if it's a custom field

      // Collect raw attributes
      const additionalAttrs = Array.from($firstTd[0].attributes).reduce(
        (attrs, { name, value }) => {
          if (
            name.startsWith("data-") &&
            // Exclude known attributes
            name !== "data-field-id" &&
            name !== "data-field-type"
          ) {
            attrs[name] = value;
          }
          return attrs;
        },
        {}
      );

      const fieldObj = {
        fieldType: fieldType,
        fieldId: $firstTd.data("field-id"),
        // As of now, only formula fields have fieldValue. Others like custom fields do not.
        // Instead, their fieldValue is stored as fieldId during creation.
        // (See ComparisonTable.js saveField() method in the CUSTOM FIELD section)
        // fieldValue: $firstTd.data("formula") || "",
        title: $firstTd.clone().children().remove().end().text().trim(),
      };
      if (additionalAttrs && Object.keys(additionalAttrs).length > 0) {
        fieldObj.additionalAttrs = additionalAttrs;
      }
      state.fields.push(fieldObj);

      // Capture cells for custom fields only
      if (fieldType === "custom-field") {
        // Capture custom field cells EXCEPT THE FIRST TD
        $tr.find("td:not(:first)").each((_, td) => {
          const $td = $(td);
          state.cells.push({
            // fieldId and entryId act as composite keys to identify the cell
            fieldId: $firstTd.data("field-id"),
            // Extract the entryId from the corresponding TH
            entryId: this.comparisonTable.comparisonTable
              .find(`thead th:nth-child(${$td.index() + 1})`)
              .data("id"),
            value: $td.text().trim(),
          });
        });
      }
    });

    return state;
  }

  pushUndoState() {
    const state = this._captureState();
    this.undoStack.push(state);
    this.redoStack = []; // Reset redo stack on new action
    this.updateUndoRedoButtons();
  }

  undo() {
    if (!this.undoStack.length) return;
    const prevState = this.undoStack.pop();
    const currentState = this._captureState();
    this.redoStack.push(currentState);
    this._applyState(prevState);
    this.updateUndoRedoButtons();
    // this.markDirty();
  }

  redo() {
    if (!this.redoStack.length) return;
    const nextState = this.redoStack.pop();
    const currentState = this._captureState();
    this.undoStack.push(currentState);
    this._applyState(nextState);
    this.updateUndoRedoButtons();
    // this.markDirty();
  }

  _applyState(state) {
    // Clear existing table content
    this.comparisonTable.comparisonTable.find("thead th:not(:first)").remove();
    this.comparisonTable.comparisonTable.find("tbody").empty();

    // Rebuild headers
    state.headers.forEach((header) => {
      this.comparisonTable.addEntryColumn(
        header.source,
        header.modelId,
        header.entryId,
        header.id,
        header.label
      );
    });

    // Rebuild fields
    state.fields.forEach((field) => {
      let tag = null;

      if (field.fieldType === "math-formula") {
        tag = field.additionalAttrs["data-formula"] || null; // For formula fields, use formula as tag
      } else {
        tag = field.fieldId; // For custom fields, use fieldId as tag
      }

      this.comparisonTable.addFieldRow({
        fieldType: field.fieldType,
        fieldId: field.fieldId,
        title: field.title,
        tag: tag,
        additionalAttrs: {
          ...(field.additionalAttrs ? field.additionalAttrs : {}),
        },
      });
    });

    // Rebuild custom field cells
    state.cells?.forEach((cell) => {
      this.comparisonTable
        .getComparisonCellByDataIdAndFieldId(cell.entryId, cell.fieldId)
        .text(cell.value);
    });

    // Refresh data
    this.comparisonTable.dataManager.fetchCellData();
  }

  updateUndoRedoButtons() {
    const canUndo = this.undoStack.length > 0;
    const canRedo = this.redoStack.length > 0;
    $("#undoBtn").prop("disabled", !canUndo);
    $("#redoBtn").prop("disabled", !canRedo);
  }
}
