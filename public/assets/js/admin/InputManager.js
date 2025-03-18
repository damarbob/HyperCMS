import InputCreator from "./InputCreator";
import InputPopulator from "./InputPopulator";

export default class InputManager {
    constructor(container) {
        this.container = container;
        this.inputCreator = new InputCreator(container);
        this.inputPopulator = new InputPopulator(container);
    }

    createInputs(fields) {
        this.inputCreator.create(fields);
    }

    populateInputs(fields) {
        this.inputPopulator.populate(fields);
    }
}