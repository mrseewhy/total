import { Outlet } from "react-router";
import Footer from "../components/site/Footer";
import Navbar from "../components/site/Navbar";
import { Toaster } from "sonner";

const MainLayout = () => {
  return (
    <div className="min-h-screen bg-[#faf9f6] text-[#26392f]">
      <Navbar />
      <Outlet />
      <Footer />
      <Toaster position="top-right" />
    </div>
  );
};

export default MainLayout;
