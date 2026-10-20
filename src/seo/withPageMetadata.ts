import type { RouteObject } from "react-router";
import { publicPageLoader } from "./pageData";

/** Attach the shared metadata loader to public page leaves, preserving custom loaders. */
export function withPageMetadata(routes: RouteObject[]): RouteObject[] {
  return routes.map((route) =>
    route.children
      ? { ...route, children: withPageMetadata(route.children) }
      : { ...route, loader: route.loader ?? publicPageLoader },
  );
}
