import $ from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";
import { ComparisonTable } from "./ComparisonTable.js";

export class ModalManager {
  /**
   * A modal manager for handling modals in the comparison table.
   * @param {ComparisonTable} comparisonTable
   */
  constructor(comparisonTable) {
    this.comparisonTable = comparisonTable;

    // Import language
    this.lang = comparisonTable.lang;
    this.langAdmin = comparisonTable.langAdmin;

    // Create modals
    this.addEntryModal = this.createEntryModal();
    this.addFieldModal = this.createFieldModal();
    this.previewEntryModal = this.createPreviewEntryModal();
  }

  /**
   *
   * @returns {}
   */
  createEntryModal() {
    return $("<div>", {
      class: "modal",
      id: "addEntryModal",
      append: [
        $("<div>", {
          class: "modal-background",
        }),
        $("<div>", {
          class: "modal-card",
          append: [
            $("<header>", {
              class: "modal-card-head",
              append: [
                (this.entryModalLabel = $("<p>", {
                  class: "modal-card-title",
                  text: this.lang.addEntry,
                })),
                $("<button>", {
                  class: "delete",
                  title: this.langAdmin.close,
                  "aria-label": this.langAdmin.close,
                }),
              ],
            }),
            $("<section>", {
              class: "modal-card-body",
              append: [
                $("<div>", {
                  class: "field",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.lang.dataSource,
                    }),
                    $("<div>", {
                      class: "control",
                      append: [
                        $("<div>", {
                          class: "select",
                          append: [
                            (this.dataSourceSelect = $("<select>", {
                              id: "dataSourceSelect",
                            })),
                          ],
                        }),
                      ],
                    }),
                  ],
                }),
                $("<div>", {
                  class: "field",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.lang.selectModel,
                    }),
                    $("<div>", {
                      class: "control",
                      append: [
                        $("<div>", {
                          class: "select",
                          append: [
                            (this.modelSelect = $("<select>", {
                              id: "modelSelect",
                              append: [
                                $("<option>", {
                                  value: "",
                                  text: this.lang.chooseModel,
                                }),
                              ],
                            })),
                          ],
                        }),
                      ],
                    }),
                  ],
                }),
                $("<div>", {
                  class: "field",
                  append: [
                    (this.modelSelectAllFields = $("<button>", {
                      id: "modelSelectAllFields",
                      class: "button is-small",
                      text: this.lang.selectAll,
                    }).hide()),
                  ],
                }),
                $("<div>", {
                  class: "field",
                  append: [
                    (this.modelFieldCheckboxes = $("<div>", {
                      class: "checkboxes",
                      id: "modelFieldCheckboxes",
                    })),
                  ],
                }),
                $("<div>", {
                  class: "field",
                  id: "fieldEntrySelect",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.lang.selectEntry,
                    }),
                    $("<div>", {
                      class: "field has-addons",
                      append: [
                        $("<div>", {
                          class: "control",
                          append: [
                            $("<div>", {
                              class: "select",
                              append: [
                                (this.entrySelect = $("<select>", {
                                  id: "entrySelect",
                                  append: [
                                    $("<option>", {
                                      value: "",
                                      text: this.lang.chooseEntry,
                                    }),
                                  ],
                                })),
                              ],
                            }),
                          ],
                        }),
                        $("<div>", {
                          class: "control",
                          append: [
                            $("<button>", {
                              id: "previewEntry",
                              class: "button",
                              title: this.langAdmin.preview,
                              disabled: true,
                              append: [
                                $("<span>", {
                                  class: "icon",
                                }).append(
                                  $("<i>", {
                                    class: "fa-solid fa-eye",
                                  })
                                ),
                              ],
                            }),
                          ],
                        }),
                      ],
                    }),
                    $("<p>", {
                      class: "help",
                      text: this.lang.requiredUnlessUseExternalDataSource,
                    }),
                  ],
                }),
                $("<div>", {
                  class: "field",
                  id: "fieldEntryId",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.langAdmin.id,
                    }),
                    $("<div>", {
                      class: "control",
                      append: [
                        (this.dataId = $("<input>", {
                          type: "text",
                          id: "dataId",
                          class: "input",
                          placeholder:
                            window.hyper.util.text.replacePlaceholders(
                              this.lang.egx,
                              { x: "john_doe" }
                            ),
                        })),
                      ],
                    }),
                    $("<p>", {
                      class: "help",
                      text: this.lang.optionalUnlessUseExternalDataSource,
                    }),
                  ],
                }),
                $("<div>", {
                  class: "field",
                  id: "fieldEntryLabel",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.langAdmin.label,
                    }),
                    $("<div>", {
                      class: "control",
                      append: [
                        (this.entryLabel = $("<input>", {
                          type: "text",
                          id: "entryLabel",
                          class: "input",
                          placeholder:
                            window.hyper.util.text.replacePlaceholders(
                              this.lang.egx,
                              { x: "John Doe" }
                            ),
                        })),
                      ],
                    }),
                    $("<p>", {
                      class: "help",
                      text: this.lang.optionalLabelForDisplayPurposes,
                    }),
                  ],
                }),
              ],
            }),
            $("<footer>", {
              class: "modal-card-foot",
              append: [
                $("<div>", {
                  class: "buttons",
                  append: [
                    $("<button>", {
                      id: "saveEntryBtn",
                      class: "button is-success",
                      text: this.langAdmin.save,
                    }),
                    $("<button>", {
                      class: "button modal-dismiss",
                      text: this.langAdmin.cancel,
                    }),
                  ],
                }),
              ],
            }),
          ],
        }),
      ],
    });
  }

  createPreviewEntryModal() {
    return $("<div>", {
      class: "modal",
      id: "previewEntryModal",
      append: [
        $("<div>", {
          class: "modal-background",
        }),
        $("<div>", {
          class: "modal-card",
          append: [
            $("<header>", {
              class: "modal-card-head",
              append: [
                $("<p>", {
                  class: "modal-card-title",
                  text: this.lang.previewEntry,
                }),
                $("<button>", {
                  class: "delete",
                  title: this.langAdmin.close,
                  "aria-label": this.langAdmin.close,
                }),
              ],
            }),
            $("<section>", {
              class: "modal-card-body",
              append: [
                $("<div>", {
                  id: "previewEntryContent",
                  text: this.lang.contentHere,
                }),
              ],
            }),
            $("<footer>", {
              class: "modal-card-foot",
              append: [
                $("<div>", {
                  class: "buttons",
                  append: [
                    $("<button>", {
                      class: "button modal-dismiss",
                      text: this.langAdmin.cancel,
                    }),
                  ],
                }),
              ],
            }),
          ],
        }),
      ],
    });
  }

  createFieldModal() {
    return $("<div>", {
      class: "modal",
      id: "addFieldModal",
      append: [
        $("<div>", {
          class: "modal-background",
        }),
        $("<div>", {
          class: "modal-card",
          append: [
            $("<header>", {
              class: "modal-card-head",
              append: [
                (this.fieldModalLabel = $("<p>", {
                  class: "modal-card-title",
                  text: this.lang.addField,
                })),
                $("<button>", {
                  class: "delete",
                  title: this.langAdmin.close,
                  "aria-label": this.langAdmin.close,
                }),
              ],
            }),
            $("<section>", {
              class: "modal-card-body",
              append: [
                $("<div>", {
                  class: "field",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.lang.fieldType,
                    }),
                    $("<div>", {
                      class: "control",
                      append: [
                        $("<div>", {
                          class: "select",
                          append: [
                            (this.fieldTypeSelect = $("<select>", {
                              id: "fieldTypeSelect",
                              append: [
                                $("<option>", {
                                  value: "",
                                  text: this.lang.chooseFieldType,
                                }),
                                $("<option>", {
                                  value: "field",
                                  text: this.lang.field,
                                }),
                                $("<option>", {
                                  value: "custom-field",
                                  text: this.lang.customField,
                                }),
                                $("<option>", {
                                  value: "math-formula",
                                  text: this.lang.mathFormula,
                                }),
                              ],
                            })),
                          ],
                        }),
                      ],
                    }),
                  ],
                }),
                (this.fieldSelectGroup = $("<div>", {
                  class: "field",
                  id: "fieldSelectGroup",
                  style: "display: none;",
                  append: [
                    $("<label>", {
                      class: "label",
                      text: this.lang.selectField,
                    }),
                    $("<div>", {
                      class: "control",
                      append: [
                        $("<div>", {
                          class: "select",
                          append: [
                            (this.fieldSelect = $("<select>", {
                              id: "fieldSelect",
                              append: [
                                $("<option>", {
                                  value: "",
                                  text: this.lang.chooseField,
                                }),
                              ],
                            })),
                          ],
                        }),
                      ],
                    }),
                  ],
                })),
                (this.fieldFormulaGroup = $("<div>", {
                  class: "field",
                  id: "fieldFormulaGroup",
                  style: "display: none;",
                  append: [
                    (this.input1Label = $("<label>", {
                      class: "label",
                      text: "Input 1",
                    })),
                    $("<div>", {
                      class: "control",
                      append: [
                        (this.input1Input = $("<input>", {
                          type: "text",
                          class: "input",
                          placeholder: "Input 1",
                        })),
                      ],
                    }),
                    (this.input2Label = $("<label>", {
                      class: "label",
                      text: "Input 2",
                    })),
                    $("<div>", {
                      class: "control",
                      append: [
                        (this.input2Input = $("<input>", {
                          type: "text",
                          class: "input",
                          placeholder: "Input 2",
                        })),
                      ],
                    }),
                  ],
                })),
              ],
            }),
            $("<footer>", {
              class: "modal-card-foot",
              append: [
                $("<div>", {
                  class: "buttons",
                  append: [
                    $("<button>", {
                      id: "saveFieldBtn",
                      class: "button is-success",
                      text: this.langAdmin.save,
                    }),
                    $("<button>", {
                      class: "button modal-dismiss",
                      text: this.langAdmin.cancel,
                    }),
                  ],
                }),
              ],
            }),
          ],
        }),
      ],
    });
  }

  openModal($modal) {
    $modal.addClass("is-active");
  }

  closeModal($modal) {
    $modal.removeClass("is-active");
  }

  openEntryModal({ mode, id, data }) {
    if (mode === "edit") {
      this.entryModalLabel.text(this.lang.editEntry);
    } else {
      this.entryModalLabel.text(this.lang.addEntry);
    }

    this.openModal(this.addEntryModal);
    $(this.addEntryModal).attr({
      "data-mode": mode,
      "data-id": id,
    });
  }

  openFieldModal({ mode, fieldId }) {
    if (mode === "edit") {
      this.fieldModalLabel.text(this.lang.editField);
    } else {
      this.fieldModalLabel.text(this.lang.addField);
    }
    this.openModal(this.addFieldModal);
    $(this.addFieldModal).attr({
      "data-mode": mode,
      "data-field-id": fieldId,
    });
  }
}
