import AdminPages from "../pages/admin/AdminPages";
import { contentModules } from "../content/modules";
import PublicContentPage from "../pages/site/PublicContentPage";
import AdminSeo from "../pages/admin/AdminSeo";
import AdminActivity from "../pages/admin/AdminActivity";
import SeoBoundary, { SeoError } from "../seo/SeoBoundary";
import { publicRoutes } from "./publicRoutes";
import { withPageMetadata } from "../seo/withPageMetadata";
import AdminUsers from "../pages/admin/AdminUsers";
import AdminRedirect from "../components/auth/AdminRedirect";
import RequireAuth from "../components/auth/RequireAuth";
import LoginPage from "../pages/auth/LoginPage";
import ForgotPasswordPage from "../pages/auth/ForgotPasswordPage";
import ResetPasswordPage from "../pages/auth/ResetPasswordPage";
import AdminProfile from "../pages/admin/AdminProfile";
import { createBrowserRouter, Navigate } from "react-router";
import MainLayout from "../layouts/MainLayout";
import AdminDashboard from "../pages/admin/AdminDashboard";
import AdminDashboardLayout from "../layouts/AdminDashboardLayout";
import AdminContacts from "../pages/admin/AdminContacts";
import AdminSettings from "../pages/admin/AdminSettings";
import AdminNewsletters from "../pages/admin/AdminNewsletters";

export const Router = createBrowserRouter([
  {
    element: <SeoBoundary />,
    errorElement: <SeoError />,
    HydrateFallback: () => (
      <p className="p-8" role="status">
        Loading…
      </p>
    ),
    children: [
      { path: "/admin", element: <AdminRedirect /> },
      { path: "/dashboard", element: <AdminRedirect /> },
      { path: "/site-admin/login", element: <LoginPage /> },
      { path: "/site-admin/forgot-password", element: <ForgotPasswordPage /> },
      { path: "/site-admin/reset-password", element: <ResetPasswordPage /> },
      {
        path: "/",
        element: <MainLayout />,
        children: withPageMetadata([
          ...publicRoutes,
          ...contentModules.flatMap((module) => [
            { path: module.path, element: <PublicContentPage /> },
            { path: `${module.path}/:slug`, element: <PublicContentPage /> },
          ]),
        ]),
      },
      {
        element: <RequireAuth />,
        HydrateFallback: () => (
          <p role="status" className="p-8 text-sm text-gray-500">
            Loading dashboard…
          </p>
        ),
        children: [
          {
            path: "/site-admin",
            element: <AdminDashboardLayout />,
            children: [
              { index: true, element: <AdminDashboard /> },
              { path: "seo", element: <AdminSeo /> },
              { path: "activity", element: <AdminActivity /> },
              ...contentModules.flatMap(({ id: kind }) => [
                {
                  path: kind,
                  lazy: async () => ({
                    Component: (await import("../pages/admin/AdminContent"))
                      .default,
                  }),
                },
                {
                  path: `${kind}/new`,
                  lazy: async () => ({
                    Component: (await import("../pages/admin/AdminContent"))
                      .default,
                  }),
                },
                {
                  path: `${kind}/:id/edit`,
                  lazy: async () => ({
                    Component: (await import("../pages/admin/AdminContent"))
                      .default,
                  }),
                },
                {
                  path: `${kind}/:id/preview`,
                  lazy: async () => ({
                    Component: (await import("../pages/admin/AdminContent"))
                      .default,
                  }),
                },
              ]),
              {
                path: "media",
                lazy: async () => ({
                  Component: (await import("../pages/admin/AdminMedia"))
                    .default,
                }),
              },
              { path: "pages", element: <AdminPages /> },
              { path: "users", element: <AdminUsers /> },
              { path: "newsletters", element: <AdminNewsletters /> },
              { path: "contacts", element: <AdminContacts /> },
              { path: "settings", element: <AdminSettings /> },
              { path: "profile", element: <AdminProfile /> },
              { path: "*", element: <Navigate to="/site-admin" replace /> },
            ],
          },
        ],
      },
    ],
  },
]);
