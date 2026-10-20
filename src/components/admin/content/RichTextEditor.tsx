import { useState } from "react";
import { BlockNoteSchema, defaultBlockSpecs } from "@blocknote/core";
import type { PartialBlock } from "@blocknote/core";
import { FormattingToolbar, useCreateBlockNote } from "@blocknote/react";
import { BlockNoteView } from "@blocknote/mantine";
import "@blocknote/core/fonts/inter.css";
import "@blocknote/mantine/style.css";
import { ImagePlus } from "lucide-react";
import { toast } from "sonner";
import { uploadMedia } from "../../../api/content";
import MediaPicker from "../media/MediaPicker";
const editorSchema = BlockNoteSchema.create({
  blockSpecs: {
    paragraph: defaultBlockSpecs.paragraph,
    heading: defaultBlockSpecs.heading,
    bulletListItem: defaultBlockSpecs.bulletListItem,
    numberedListItem: defaultBlockSpecs.numberedListItem,
    checkListItem: defaultBlockSpecs.checkListItem,
    quote: defaultBlockSpecs.quote,
    codeBlock: defaultBlockSpecs.codeBlock,
    divider: defaultBlockSpecs.divider,
    image: defaultBlockSpecs.image,
    table: defaultBlockSpecs.table,
  },
});
type EditorBlock = typeof editorSchema.PartialBlock;
export default function RichTextEditor({
  initialContent,
  onChange,
  readOnly = false,
}: {
  initialContent: PartialBlock[];
  onChange?: (blocks: PartialBlock[]) => void;
  readOnly?: boolean;
}) {
  const [picker, setPicker] = useState(false);
  const editor = useCreateBlockNote({
    schema: editorSchema,
    initialContent: initialContent.length
      ? (initialContent as EditorBlock[])
      : undefined,
    uploadFile: async (file) => {
      try {
        return (await uploadMedia(file)).url;
      } catch (e) {
        toast.error(e instanceof Error ? e.message : "Upload failed");
        throw e;
      }
    },
  });
  return (
    <div className={`richtext-editor ${readOnly ? "editor-preview" : ""}`}>
      <BlockNoteView
        editor={editor}
        theme="light"
        editable={!readOnly}
        formattingToolbar={false}
        filePanel={false}
        onChange={() => onChange?.(editor.document)}
      >
        {!readOnly && (
          <div className="pinned-toolbar">
            <FormattingToolbar />
            <button
              type="button"
              onClick={() => setPicker(true)}
              className="ml-2 inline-flex shrink-0 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700"
            >
              <ImagePlus className="size-4" />
              Insert image
            </button>
          </div>
        )}
      </BlockNoteView>
      {picker && (
        <MediaPicker
          onClose={() => setPicker(false)}
          onChoose={(items) => {
            for (const media of items)
              editor.insertBlocks(
                [
                  {
                    type: "image",
                    props: {
                      url: media.url,
                      name: media.title,
                      caption: media.caption,
                    },
                  },
                ],
                editor.getTextCursorPosition().block,
                "after",
              );
          }}
        />
      )}
    </div>
  );
}
