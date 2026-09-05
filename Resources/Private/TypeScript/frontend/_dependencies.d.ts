// Ambient declarations for the libraries this extension does not own.
//
// Only vendor specifiers belong here. The extension's own modules are resolved
// by the "paths" entry of Build/tsconfig.json straight to their TypeScript
// source, so a consumer is checked against the real exports rather than against
// a hand-written copy that drifts.
//
// - "cropperjs" is the CropperJS 1.6.1 build EXT:core publishes; the extension's
//   Configuration/JavaScriptModules.php declares "core" as a dependency, which
//   is what makes the specifier resolvable on a profile editing page.
// - The six "@ckeditor/*" specifiers are the CKEditor 5 bundles of
//   EXT:rte_ckeditor, mapped one by one in the same file.
//
// Both are declared with exactly the surface this repository calls, and no
// more: a declaration is the contract a core upgrade is checked against, and a
// member nothing imports is a claim nobody verifies.

declare module "cropperjs" {
  /**
   * The crop rectangle in the natural pixels of the source image, which is what
   * the upload is rasterised at. CropperJS answers with zeroes while it is not
   * ready, never with nothing.
   */
  export interface CropperData {
    height: number;
    width: number;
    x: number;
    y: number;
  }

  /**
   * The crop box in the pixels the stage lays it out with. Every member is
   * optional because CropperJS returns an empty object before it is ready.
   */
  export interface CropperBoxData {
    height?: number;
    left?: number;
    top?: number;
    width?: number;
  }

  export interface CropperOptions {
    aspectRatio?: number;
    autoCropArea?: number;
    background?: boolean;
    checkCrossOrigin?: boolean;
    dragMode?: "crop" | "move" | "none";
    /** Raised for a source the browser cannot decode; `ready` never follows it. */
    error?: () => void;
    ready?: () => void;
    responsive?: boolean;
    viewMode?: 0 | 1 | 2 | 3;
    zoomable?: boolean;
  }

  export default class Cropper {
    constructor(image: HTMLImageElement, options?: CropperOptions);
    destroy(): void;
    getCropBoxData(): CropperBoxData;
    /** Null when the crop is not ready, or where there is no canvas element. */
    getCroppedCanvas(options?: { width?: number }): HTMLCanvasElement | null;
    getData(rounded?: boolean): CropperData;
  }
}

declare module "@ckeditor/ckeditor5-basic-styles" {
  export const Bold: unknown;
  export const Italic: unknown;
}

declare module "@ckeditor/ckeditor5-editor-classic" {
  interface EditorModelDocument {
    on(eventName: "change:data", listener: () => void): void;
  }

  export interface ClassicEditorInstance {
    destroy(): Promise<void>;
    editing: { view: { focus: () => void } };
    getData(): string;
    model: { document: EditorModelDocument };
    setData(value: string): void;
  }

  export const ClassicEditor: {
    create(
      field: HTMLTextAreaElement,
      configuration: Record<string, unknown>,
    ): Promise<ClassicEditorInstance>;
  };
}

declare module "@ckeditor/ckeditor5-essentials" {
  export const Essentials: unknown;
}

declare module "@ckeditor/ckeditor5-link" {
  export const Link: unknown;
}

declare module "@ckeditor/ckeditor5-list" {
  export const List: unknown;
}

declare module "@ckeditor/ckeditor5-paragraph" {
  export const Paragraph: unknown;
}
