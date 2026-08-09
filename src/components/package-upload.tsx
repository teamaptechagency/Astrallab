"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";

// Uploads a release artefact straight to the streaming route.
//
// Sends the File as the raw request body rather than multipart/form-data —
// the server streams it to disk and hashes it in flight, which multipart
// parsing would defeat by buffering. XMLHttpRequest rather than fetch, purely
// because it reports upload progress and fetch still does not.

export function PackageUpload({
  releaseId,
  hasPackage,
}: {
  releaseId: string;
  hasPackage: boolean;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const router = useRouter();
  const [progress, setProgress] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  function upload(file: File) {
    setError(null);
    setProgress(0);

    const xhr = new XMLHttpRequest();
    xhr.open("POST", `/api/admin/releases/${releaseId}/package`);
    xhr.setRequestHeader("Content-Type", "application/zip");

    xhr.upload.addEventListener("progress", (e) => {
      if (e.lengthComputable) setProgress(Math.round((e.loaded / e.total) * 100));
    });

    xhr.addEventListener("load", () => {
      setProgress(null);
      if (xhr.status >= 200 && xhr.status < 300) {
        router.refresh();
      } else {
        let message = `Upload failed (${xhr.status})`;
        try {
          const body = JSON.parse(xhr.responseText);
          if (body?.error) message = body.error.replace(/_/g, " ");
        } catch {
          // Non-JSON error body — the status code is all we have.
        }
        setError(message);
      }
    });

    xhr.addEventListener("error", () => {
      setProgress(null);
      setError("Upload failed — connection lost");
    });

    xhr.send(file);
  }

  return (
    <div className="flex flex-col gap-1">
      <input
        ref={inputRef}
        type="file"
        accept=".zip,application/zip"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) upload(file);
          // Reset so choosing the same file twice still fires a change event.
          e.target.value = "";
        }}
      />
      <button
        type="button"
        disabled={progress !== null}
        onClick={() => inputRef.current?.click()}
        className="btn-ghost !px-2 !py-1 text-xs"
      >
        {progress !== null ? `${progress}%` : hasPackage ? "Replace file" : "Upload file"}
      </button>
      {error && <span className="text-[11px] text-red-600">{error}</span>}
    </div>
  );
}
