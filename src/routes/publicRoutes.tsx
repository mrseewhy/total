import type { RouteObject } from "react-router";
import HomePage from "../pages/site/HomePage";
import AboutPage from "../pages/site/AboutPage";
import ContactPage from "../pages/site/ContactPage";
import PublicContentPage from "../pages/site/PublicContentPage";
import PageLayout from "../layouts/PageLayout";

// Add public React routes here. Vite automatically shares concrete paths with PHP.
// handle.seo is optional: missing defaults are generated from the path and site settings.
export const publicRoutes: RouteObject[] = [
  {
    index: true,
    element: <HomePage />,
    handle: {
      seo: {
        label: "Home",
        title: "Homes for Sale & Rent in Lagos | {siteName}",
        description:
          "Find a home that fits the life you want to live. Explore homes for sale and rent in Lagos with honest, local guidance.",
        heading: "More than a house. A place to belong.",
      },
    },
  },
  {
    element: <PageLayout />,
    children: [
      {
        path: "about",
        element: <AboutPage />,
        handle: {
          seo: {
            label: "About",
            title: "About Us | {siteName}",
            description:
              "Get to know Haven Real Estate, a Lagos-based team helping you buy, rent, and sell with clear advice and a personal approach.",
            heading: "Good places. Even better beginnings.",
            schema: "AboutPage",
          },
        },
      },
      {
        path: "contact",
        element: <ContactPage />,
        handle: {
          seo: {
            label: "Contact",
            title: "Contact Us | {siteName}",
            description:
              "Contact Haven Real Estate about buying, renting, or selling a home in Lagos. Share your needs and start a conversation with our team.",
            heading: "Let’s find your place.",
            schema: "ContactPage",
          },
        },
      },
    ],
  },
  { path: "*", element: <PublicContentPage /> },
];
