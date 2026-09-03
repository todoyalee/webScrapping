import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "ProxyScrape — Products",
  description: "Products scraped by the Laravel service, refreshed every 30 seconds.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        {children}
      </body>
    </html>
  );
}
