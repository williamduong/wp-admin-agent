// Adapt JSX emitted by Vite to the React instance provided by WordPress.
export const Fragment = globalThis.wp.element.Fragment;

function createJsxElement(type, props, key) {
    const { children, ...attributes } = props || {};
    if (key !== undefined) {
        attributes.key = key;
    }
    return globalThis.wp.element.createElement(type, attributes, children);
}

export const jsx = createJsxElement;
export const jsxs = createJsxElement;
