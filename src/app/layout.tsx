export const metadata = {
  title: "manage.astralab",
  description: "Licence, activation and update hub",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body
        style={{
          margin: 0,
          fontFamily: "ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif",
          background: "#0b1020",
          color: "#e6e9f2",
        }}
      >
        {children}
      </body>
    </html>
  );
}
