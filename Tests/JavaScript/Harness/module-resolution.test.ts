import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resetBody } from "../../../../../../Build/tests/dom.mjs";

/**
 * The resolve hook of "Build/tests/resolve-hook.mjs", exercised as it is used:
 * registered on node's loader thread by "register.mjs", not re-instantiated
 * here. A second instance would prove that the code can work, not that the
 * harness the other tests run under does.
 *
 * Every specifier below is imported through a variable rather than as a
 * literal, deliberately. TypeScript would have to resolve a literal one at
 * compile time, and what is under test is node's resolution - the stubs and the
 * shipped modules are not packages, and giving TypeScript a mapping for them
 * would make this test pass for the wrong reason.
 */
const importModule = async (specifier: string): Promise<Record<string, unknown>> =>
  (await import(specifier)) as Record<string, unknown>;

describe("the stubs", () => {
  it("stands in for the CKEditor bundles EXT:rte_ckeditor publishes", async () => {
    const basicStyles = await importModule("@ckeditor/ckeditor5-basic-styles");

    assert.equal(basicStyles.Bold, "stub:Bold");
    assert.equal(basicStyles.Italic, "stub:Italic");
  });

  it("collapses all six CKEditor specifiers onto one module", async () => {
    // One stub file behind six specifiers: node caches by resolved url, so the
    // plugin identities a configuration is assembled from stay comparable.
    const [essentials, link, editor] = await Promise.all([
      importModule("@ckeditor/ckeditor5-essentials"),
      importModule("@ckeditor/ckeditor5-link"),
      importModule("@ckeditor/ckeditor5-editor-classic"),
    ]);

    assert.equal(essentials.Link, link.Link);
    assert.equal(typeof editor.ClassicEditor, "object");
  });

  it("stands in for the CropperJS build EXT:core publishes", async () => {
    const module = await importModule("cropperjs");
    const StubCropper = module.default as new (
      source: Element,
      options: { aspectRatio: number },
    ) => { destroy: () => void };

    // The image inside the stage, because CropperJS 1 mounts next to the image
    // it is handed rather than into a container it is told about - so the stub
    // reports on the parent, and a test that nested them differently would be
    // asserting on the wrong element.
    const body = resetBody('<div id="stage"><img id="source" alt=""></div>');
    const source = body.querySelector("#source");
    const stage = body.querySelector("#stage");
    assert.ok(source !== null && stage !== null);

    const cropper = new StubCropper(source, { aspectRatio: 4 / 3 });
    assert.equal(stage.getAttribute("data-test-cropper"), "live");
    assert.equal(
      stage.getAttribute("data-test-cropper-ratio"),
      String(4 / 3),
    );

    cropper.destroy();
    assert.equal(stage.getAttribute("data-test-cropper"), "destroyed");
    assert.equal(stage.getAttribute("data-test-cropper-destroys"), "1");
  });
});

describe("the modules of this repository", () => {
  it("resolves the import map specifier to the TypeScript source", async () => {
    // "academic-persons" rather than the extension this test file sits in: the
    // prefix is derived from every package directory, not from the local one.
    const body = resetBody("<div data-academic-persons-detail></div>");

    await importModule("@fgtclb/academic-persons/frontend/profile.js");

    const root = body.querySelector("[data-academic-persons-detail]");
    assert.ok(root instanceof HTMLElement);
    // The module initialises on evaluation, so this asserts three things at
    // once: the specifier resolved, node stripped the types of a ".ts" file
    // reached through a ".js" specifier, and the DOM was installed before the
    // module ran.
    assert.equal(root.dataset.academicPersonsDetailInitialized, "true");
  });

  it("names the specifier and the file it looked for when there is no source", async () => {
    await assert.rejects(
      () => importModule("@fgtclb/academic-persons-edit/frontend/nowhere.js"),
      (error: Error) => {
        assert.match(
          error.message,
          /No TypeScript source for the module specifier "@fgtclb\/academic-persons-edit\/frontend\/nowhere\.js"/,
        );
        assert.match(
          error.message,
          /academic-persons-edit\/Resources\/Private\/TypeScript\/frontend\/nowhere\.ts/,
        );

        return true;
      },
    );
  });

  it("leaves a specifier that is not one of ours to node", async () => {
    // The distinction the error above exists for: a typo in a module of this
    // repository is ours to report, an unknown package is node's.
    await assert.rejects(
      () => importModule("@fgtclb/not-an-extension/frontend/anything.js"),
      /Cannot find package/,
    );
  });
});
