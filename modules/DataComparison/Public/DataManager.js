import $ from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";

export class DataManager {
  constructor(comparisonTable) {
    this.comparisonTable = comparisonTable;
    this.dataSources = JSON.parse(window.hyper?.data?.dataSources || "[]");
    if (this.comparisonTable.hConfig.environment !== "production") {
      console.log(
        "DataManager initialized with data sources:",
        this.dataSources
      );
    }

    // Import language
    this.lang = this.comparisonTable.lang;
    this.langAdmin = this.comparisonTable.langAdmin;
  }

  initDataSources() {
    this.dataSources.forEach((source) => {
      this.comparisonTable.dataSourceSelect.append(
        $("<option>").val(source.id).text(source.label)
      );
    });
    this.populateModelSelect();
  }

  populateModelSelect() {
    const models = window.hyper?.data?.models || [];
    models.forEach((model) => {
      this.comparisonTable.modelSelect.append(
        $("<option>").val(model.id).text(model.name)
      );
    });
  }

  populateEntrySelect(modelId, oldEntryId) {
    if (!modelId) return;

    $.ajax({
      url: `${this.comparisonTable.baseUrl}api/v1/entries`,
      type: "POST",
      data: {
        model_id: modelId,
      },
      success: (response) => {
        this.comparisonTable.entries = response.data;
        this.comparisonTable.entrySelect
          .empty()
          .append($("<option>").val("").text("Choose an Entry"));
        this.comparisonTable.entries.forEach((entry) => {
          this.comparisonTable.entrySelect.append(
            $("<option>")
              .val(entry.id)
              .text(
                window.hyper.util.text.replacePlaceholders(this.lang.entryx, {
                  x: entry.id,
                })
              )
          );
        });

        if (oldEntryId) {
          if (this.comparisonTable.hConfig.environment !== "production") {
            console.log("Old entry ID:", oldEntryId);
          }
          this.comparisonTable.entrySelect.val(oldEntryId).trigger("change");
        }
      },
      error: () =>
        this.comparisonTable.showError(this.lang.failedToFetchEntries),
    });
  }

  populateFieldCheckboxes(modelId) {
    this.comparisonTable.modelFieldCheckboxes.empty();
    const model = (window.hyper.data.models || []).find((m) => m.id == modelId);
    if (!model) return;

    try {
      JSON.parse(model.fields || "[]").forEach((field) => {
        if (this.comparisonTable.fieldIsExist(field.id)) return;

        this.comparisonTable.modelFieldCheckboxes.append(
          $("<label>", {
            class: "checkbox",
          })
            .append(
              $("<input>", {
                type: "checkbox",
                val: field.id,
                checked: true,
              })
            )
            .append(
              $("<span>", {
                class: "ml-2",
                text: field.label,
              })
            )
        );
      });
    } catch (e) {
      console.error("Error parsing fields:", e);
    }
  }

  fetchCellData() {
    const entries = this.comparisonTable.comparisonTable
      .find("thead th:not(:first)")
      .map((_, th) => {
        const $th = $(th);
        return {
          source: $th.data("source"),
          id: $th.data("id"),
          modelId: $th.data("model-id"),
          entryId: $th.data("entry-id"),
        };
      })
      .get();

    entries.forEach((entry) => {
      const source = this.dataSources.find((s) => s.id === entry.source);
      const model = (window.hyper.data.models || []).find(
        (m) => m.id == entry.modelId
      );

      this.comparisonTable.enableEntryLoading(entry.id);

      if (!source) {
        console.error("Data source not found for entry:", entry.id);
        this.comparisonTable.disableEntryLoading(entry.id);
        this.comparisonTable.showError(this.lang.failedToFetchEntryData);
        return;
      }

      $.ajax({
        ...source.options,
        data: {
          id: entry.id,
          entry_id: entry.entryId,
          model_id: entry.modelId,
          model: model
            ? {
                name: model.name,
                fields: JSON.parse(model.fields || "[]"),
              }
            : null,
        },
        success: (response) => {
          this.comparisonTable.disableEntryLoading(entry.id);
          this.updateCellData(response.data);
        },
        error: () => {
          this.comparisonTable.disableEntryLoading(entry.id);
          this.comparisonTable.showError(
            this.lang.failedToFetchEntryData + " " + entry.id
          );
        },
      });
    });
  }

  updateCellData(entries) {
    entries.forEach((entry) => {
      Object.entries(entry.fields).forEach(([fieldId, value]) => {
        const $cell = this.comparisonTable.findCell(entry.id, fieldId);
        if ($cell) $cell.text(value);
      });
    });
    this.comparisonTable.reevaluateFormulaCells();
  }

  populateFieldSelect(modelId) {
    const model = (window.hyper.data.models || []).find((m) => m.id == modelId);
    if (!model) return;

    try {
      const fields = JSON.parse(model.fields || "[]");
      this.comparisonTable.fieldSelect
        .empty()
        .append($("<option>").val("").text(this.lang.chooseField))
        .append(fields.map((f) => $("<option>").val(f.id).text(f.label)));
    } catch (e) {
      console.error("Error parsing fields:", e);
    }
  }
}
