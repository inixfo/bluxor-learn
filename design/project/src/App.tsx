import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { ToastProvider } from '@/components/ui/Toast';
import { AuthProvider } from '@/services/auth-context';
import { PublicLayout } from '@/layouts/PublicLayout';
import { CheckoutLayout } from '@/layouts/CheckoutLayout';
import { CustomerLayout } from '@/layouts/CustomerLayout';
import { AdminLayout } from '@/layouts/AdminLayout';
import { GuestOnlyLayout } from '@/layouts/GuestOnlyLayout';

import Home from '@/pages/Home';
import Categories from '@/pages/Categories';
import Products from '@/pages/Products';
import ProductDetail from '@/pages/ProductDetail';
import Login from '@/pages/auth/Login';
import Register from '@/pages/auth/Register';
import ForgotPassword from '@/pages/auth/ForgotPassword';
import ResetPassword from '@/pages/auth/ResetPassword';
import StaticPage from '@/pages/StaticPage';
import Contact from '@/pages/Contact';
import HelpCenter from '@/pages/HelpCenter';
import HelpArticle from '@/pages/HelpArticle';
import Faq from '@/pages/Faq';
import DownloadHelp from '@/pages/DownloadHelp';
import ResourcePage from '@/pages/ResourcePage';
import N8nAutomationLabResources from '@/pages/N8nAutomationLabResources';
import Checkout from '@/pages/checkout/Checkout';
import PurchaseSuccess from '@/pages/checkout/PurchaseSuccess';
import CustomerOverview from '@/pages/customer/Overview';
import CustomerLibrary from '@/pages/customer/Library';
import CustomerLibraryDetail from '@/pages/customer/LibraryDetail';
import CustomerOrders from '@/pages/customer/Orders';
import CustomerOrderDetail from '@/pages/customer/OrderDetail';
import CustomerDownloads from '@/pages/customer/Downloads';
import CustomerProfile from '@/pages/customer/Profile';
import AdminDashboard from '@/pages/admin/Dashboard';
import AdminProducts from '@/pages/admin/Products';
import AdminProductEditor from '@/pages/admin/ProductEditor';
import AdminResources from '@/pages/admin/Resources';
import AdminOrders from '@/pages/admin/Orders';
import AdminCustomers from '@/pages/admin/Customers';
import AdminContactInquiries from '@/pages/admin/ContactInquiries';
import AdminContactInquiryDetail from '@/pages/admin/ContactInquiryDetail';
import AdminCoupons from '@/pages/admin/Coupons';
import AdminAnalytics from '@/pages/admin/Analytics';
import AdminLandingPages from '@/pages/admin/LandingPages';
import AdminLandingPageDetail from '@/pages/admin/LandingPageDetail';
import AdminUploadLandingPage from '@/pages/admin/UploadLandingPage';
import AdminSettings from '@/pages/admin/Settings';
import AdminAuditLogs from '@/pages/admin/AuditLogs';
import AdminCategories from '@/pages/admin/Categories';
import AdminContentPages from '@/pages/admin/ContentPages';
import AdminFaqManager from '@/pages/admin/FaqManager';
import AdminHelpCenterManager from '@/pages/admin/HelpCenterManager';
import NotFound from '@/pages/NotFound';

export default function App() {
  return (
    <ToastProvider>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
          <Route element={<PublicLayout />}>
            <Route path="/" element={<Home />} />
            <Route path="/products" element={<Products />} />
            <Route path="/categories" element={<Categories />} />
            <Route path="/categories/:slug" element={<Products />} />
            <Route path="/p/:slug" element={<ProductDetail />} />
            <Route element={<GuestOnlyLayout />}>
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/forgot-password" element={<ForgotPassword />} />
            </Route>
            <Route path="/reset-password" element={<ResetPassword />} />
            <Route path="/about" element={<StaticPage />} />
            <Route path="/contact" element={<Contact />} />
            <Route path="/help" element={<HelpCenter />} />
            <Route path="/help/:categorySlug/:articleSlug" element={<HelpArticle />} />
            <Route path="/faq" element={<Faq />} />
            <Route path="/download-help" element={<DownloadHelp />} />
            <Route path="/r/:slug" element={<ResourcePage />} />
            <Route path="/resources/n8n-automation-lab" element={<N8nAutomationLabResources />} />
            <Route path="/resources/n8n-automation-lab/:projectSlug" element={<N8nAutomationLabResources />} />
            <Route path="/terms" element={<StaticPage />} />
            <Route path="/privacy" element={<StaticPage />} />
            <Route path="/refund-policy" element={<StaticPage />} />
          </Route>

          <Route element={<CheckoutLayout />}>
            <Route path="/checkout" element={<Checkout />} />
            <Route path="/checkout/success" element={<PurchaseSuccess />} />
          </Route>

          <Route element={<CustomerLayout />}>
            <Route path="/account" element={<CustomerOverview />} />
            <Route path="/account/library" element={<CustomerLibrary />} />
            <Route path="/account/library/:id" element={<CustomerLibraryDetail />} />
            <Route path="/account/orders" element={<CustomerOrders />} />
            <Route path="/account/orders/:orderNumber" element={<CustomerOrderDetail />} />
            <Route path="/account/downloads" element={<CustomerDownloads />} />
            <Route path="/account/profile" element={<CustomerProfile />} />
          </Route>

          <Route element={<AdminLayout />}>
            <Route path="/admin" element={<AdminDashboard />} />
            <Route path="/admin/products" element={<AdminProducts />} />
            <Route path="/admin/products/new" element={<AdminProductEditor />} />
            <Route path="/admin/products/:id/edit" element={<AdminProductEditor />} />
            <Route path="/admin/resources" element={<AdminResources />} />
            <Route path="/admin/categories" element={<AdminCategories />} />
            <Route path="/admin/orders" element={<AdminOrders />} />
            <Route path="/admin/customers" element={<AdminCustomers />} />
            <Route path="/admin/contact-inquiries" element={<AdminContactInquiries />} />
            <Route path="/admin/contact-inquiries/:id" element={<AdminContactInquiryDetail />} />
            <Route path="/admin/coupons" element={<AdminCoupons />} />
            <Route path="/admin/analytics" element={<AdminAnalytics />} />
            <Route path="/admin/landing-pages" element={<AdminLandingPages />} />
            <Route path="/admin/landing-pages/:id" element={<AdminLandingPageDetail />} />
            <Route path="/admin/landing-pages/upload" element={<AdminUploadLandingPage />} />
            <Route path="/admin/settings" element={<AdminSettings />} />
            <Route path="/admin/content-pages" element={<AdminContentPages />} />
            <Route path="/admin/help-center" element={<AdminHelpCenterManager />} />
            <Route path="/admin/faq" element={<AdminFaqManager />} />
            <Route path="/admin/audit-logs" element={<AdminAuditLogs />} />
          </Route>

          <Route path="*" element={<NotFound />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </ToastProvider>
  );
}
