import "./globals.css";

export const metadata = {
  title: "manage.astralab",
  description: "Licence, update and business management for Astra Lab",
};

export const viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#12a06d",
};

// Deliberately bare. The navigation shell lives in (app)/layout.tsx so that
// /login renders without it — a sign-in screen showing the sidebar it is
// meant to be guarding looks broken and leaks the section names.
export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
