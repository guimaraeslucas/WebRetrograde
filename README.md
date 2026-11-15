#WebRetrograde 💾

WebRetrograde is a PHP-based web proxy and rendering engine designed to bridge the gap between the modern web and vintage computing. It dynamically converts heavy, modern websites into clean, lightweight HTML 4, making them perfectly readable and navigable on classic browsers.

Whether you are dusting off an old Macintosh running Internet Explorer 4 or browsing on a vintage PC with Netscape Navigator 3.1, WebRetrograde reformats the web so it looks pleasant and performs beautifully on legacy hardware.

✨ Features
True Retro Compatibility: Outputs standard HTML 4. Carefully tailored for seamless navigation on Netscape Navigator 3.1, Netscape Communicator 4.x, Internet Explorer 4 for Mac, and newer browsers.
Smart Memory Pagination: Modern websites are massive. WebRetrograde automatically splits large pages into smaller, paginated sections to prevent memory corruption and browser crashes on older applications with limited RAM.
Intelligent Auto-Theming: Extracts dominant colors and design characteristics from modern pages to automatically style the retro output, maintaining the original site's "feel" without the CSS bloat.
Auto-Generated Navigation: Parses modern complex menus and automatically builds a simple, accessible navigation bar optimized for vintage screens.
Interactive Web Support: It doesn't just display text; it supports cookies, HTML forms, and HTTP redirects, allowing you to actually interact with modern web services.
Built-in DuckDuckGo Search: Features an integrated search portal using DuckDuckGo to easily query the web straight from your retro machine.
GitHub Repository Explorer: Includes specialized rendering logic to browse GitHub repositories comfortably on vintage systems.
Content Resizing: Automatically resizes media and adjusts layouts to fit nicely on classic 640x480 or 800x600 displays.

🚀 Getting Started
Prerequisites
A web server running PHP 8.3 or higher.
Enable the DOM and cURL PHP extensions.

Installation
Clone this repository to your web server:
git clone https://github.com/guimaraeslucas/WebRetrograde.git

Navigate to the directory and configure your local server environment to point to the index.php file.
Access the proxy via your vintage machine by typing your server's local IP address or domain name into the browser (e.g., http://192.168.1.100/WebRetrograde).

🖥️ How it Works
When you request a URL through WebRetrograde, the PHP script acts as a middleman. It fetches the modern HTML, strips out unsupported CSS and JavaScript, reconstructs the DOM using classic HTML tables and semantic tags, processes forms to route back through the proxy, and finally chunks the output to ensure the vintage browser's buffer isn't overwhelmed.

🤝 Contributing
Contributions, issues, and feature requests are welcome! If you have ideas on how to improve parsing for specific modern frameworks or enhance compatibility with other legacy browsers (like early versions of Opera or Mosaic), feel free to open a pull request.

📝 License
This project is open-source and available under the MPL-2 License.
