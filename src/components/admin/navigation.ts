import { contentModules } from "../../content/modules";
import {
  LayoutDashboard,
  Search,
  Activity,
  Mail,
  Newspaper,
  Users,
  FileText,
  Images,
  CalendarDays,
  House,
  BriefcaseBusiness,
  MessageSquare,
  Folder,
} from "lucide-react";

import type { LucideIcon } from "lucide-react";
const icons: Record<string, LucideIcon> = {
  FileText,
  BriefcaseBusiness,
  CalendarDays,
  House,
  MessageSquare,
  Folder,
};
export const adminNavigation: {
  to: string;
  label: string;
  icon: LucideIcon;
  end: boolean;
  adminOnly?: boolean;
}[] = [
  { to: "/site-admin", label: "Dashboard", icon: LayoutDashboard, end: true },
  {
    to: "/site-admin/newsletters",
    label: "Subscribers",
    icon: Newspaper,
    end: false,
  },
  {
    to: "/site-admin/contacts",
    label: "Contact Inquiries",
    icon: Mail,
    end: false,
  },
  { to: "/site-admin/media", label: "Media Library", icon: Images, end: false },
  {
    to: "/site-admin/users",
    label: "Users",
    icon: Users,
    end: false,
    adminOnly: true,
  },
  ...contentModules.map((module) => ({
    to: `/site-admin/${module.id}`,
    label: module.label,
    icon: icons[module.icon],
    end: false,
  })),
  { to: "/site-admin/pages", label: "Pages", icon: FileText, end: false },
  { to: "/site-admin/seo", label: "SEO Metadata", icon: Search, end: false },
  { to: "/site-admin/activity", label: "Activity", icon: Activity, end: false },
];
