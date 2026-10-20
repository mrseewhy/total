import { useEffect, useState } from "react";
import { useLocation, useParams } from "react-router";
import { getContent } from "../../api/content";
import type { ContentKind, ContentItem } from "../../api/content";
import ContentList from "../../components/admin/content/ContentList";
import ContentEditor from "../../components/admin/content/ContentEditor";
import ContentPreview from "../../components/admin/content/ContentPreview";
function LoadedContent({
  kind,
  id,
  preview,
}: {
  kind: ContentKind;
  id: number;
  preview: boolean;
}) {
  const [item, setItem] = useState<ContentItem | null>(null);
  const [error, setError] = useState("");
  const [version, setVersion] = useState(0);
  useEffect(() => {
    const abort = new AbortController();
    getContent(kind, id, abort.signal)
      .then(setItem)
      .catch((e) => {
        if (!abort.signal.aborted) setError(e.message);
      });
    return () => abort.abort();
  }, [kind, id, version]);
  if (error)
    return (
      <p
        role="alert"
        className="mt-6 rounded-lg bg-red-50 p-5 text-sm text-red-600"
      >
        {error}
        <button
          onClick={() => {
            setError("");
            setVersion((v) => v + 1);
          }}
          className="ml-3 underline"
        >
          Retry
        </button>
      </p>
    );
  if (!item)
    return (
      <p role="status" className="mt-6 text-sm text-gray-500">
        Loading content…
      </p>
    );
  return preview ? (
    <ContentPreview kind={kind} item={item} />
  ) : (
    <ContentEditor kind={kind} initial={item} />
  );
}
export default function AdminContent() {
  const { pathname } = useLocation();
  const { id } = useParams();
  const kind = pathname.split("/")[2] as ContentKind;
  if (id) {
    if (!/^\d+$/.test(id) || Number(id) < 1)
      return <p className="mt-6">Invalid content ID.</p>;
    return (
      <LoadedContent
        key={pathname}
        kind={kind}
        id={Number(id)}
        preview={pathname.endsWith("/preview")}
      />
    );
  }
  if (pathname.endsWith("/new"))
    return <ContentEditor key={kind} kind={kind} />;
  return <ContentList key={kind} kind={kind} />;
}
