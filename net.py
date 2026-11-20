import html
import sys
from PyQt6.QtCore import Qt, QSize, QRect, QPoint, QTimer, QUrl, pyqtSignal
from PyQt6.QtGui import (
    QAction,
    QColor,
    QFont,
    QFontDatabase,
    QIcon,
    QKeySequence,
    QLinearGradient,
    QPainter,
    QPalette,
    QPen,
    QPixmap,
    QPolygon,
)
from PyQt6.QtWidgets import (
    QApplication,
    QCheckBox,
    QComboBox,
    QDialog,
    QDialogButtonBox,
    QFileDialog,
    QFrame,
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QListWidget,
    QListWidgetItem,
    QMainWindow,
    QMenu,
    QMessageBox,
    QPlainTextEdit,
    QProgressBar,
    QPushButton,
    QSizePolicy,
    QStatusBar,
    QStyle,
    QStyleFactory,
    QStyleOptionButton,
    QVBoxLayout,
    QWidget,
)
from PyQt6.QtWebEngineWidgets import QWebEngineView


DEFAULT_HOME_URL = "https://frogfind.com"
DEFAULT_SEARCH_URL = "https://frogfind.com"
DEFAULT_GUIDE_URL = "https://wiby.me"

DEFAULT_BOOKMARKS = [
    ("FrogFind! Search", "https://frogfind.com"),
    ("Wiby - Old Web Search", "https://wiby.me"),
    ("68k.news - Headlines", "http://68k.news"),
    ("The Old Net", "https://theoldnet.com"),
    ("NPR Text Only", "https://text.npr.org"),
    ("Welcome to Netscape", "about:netscape"),
]

PERSONAL_TOOLBAR_LINKS = [
    ("Home", DEFAULT_HOME_URL, "home"),
    ("FrogFind", "https://frogfind.com", "search"),
    ("Wiby", "https://wiby.me", "guide"),
    ("68k News", "http://68k.news", "page"),
    ("The Old Net", "https://theoldnet.com", "page"),
]

DIRECTORY_LINKS = [
    ("Retro Search Engines", [
        ("FrogFind!", "https://frogfind.com"),
        ("Wiby", "https://wiby.me"),
    ]),
    ("Classic News & Reference", [
        ("68k.news", "http://68k.news"),
        ("NPR Text", "https://text.npr.org"),
        ("CNN Lite", "https://lite.cnn.com"),
        ("Wikipedia", "https://en.wikipedia.org"),
    ]),
    ("Web Preservation", [
        ("The Old Net", "https://theoldnet.com"),
        ("Internet Archive", "https://web.archive.org"),
    ]),
]


def zacos_font(family_preferred: str, pixel_size: int, bold: bool = False) -> QFont:
    """Return a crisp pixel-aligned ZacOS 9 font when installed, else a clean fallback."""
    available = set(QFontDatabase.families())
    if family_preferred in available:
        font = QFont(family_preferred)
    elif "ZacOS 9 System" in available:
        font = QFont("ZacOS 9 System")
    elif "ZacOS 9 Views" in available:
        font = QFont("ZacOS 9 Views")
    else:
        font = QFont("Sans Serif")
    font.setPixelSize(pixel_size)
    font.setBold(bold)
    font.setStyleStrategy(QFont.StyleStrategy.NoAntialias)
    return font


def apply_zacos9_theme(app: QApplication) -> None:
    """Activate the native ZacOS 9 Qt style and pixel fonts."""
    keys = {k.lower(): k for k in QStyleFactory.keys()}
    if "zacos9" in keys:
        app.setStyle(keys["zacos9"])
    app.setFont(zacos_font("ZacOS 9 System", 12))


def platinum_colors(palette: QPalette) -> dict[str, QColor]:
    """Read standard Platinum colors from the active ZacOS 9 QPalette."""
    return {
        "window": palette.color(QPalette.ColorRole.Window),
        "button": palette.color(QPalette.ColorRole.Button),
        "light": palette.color(QPalette.ColorRole.Light),
        "midlight": palette.color(QPalette.ColorRole.Midlight),
        "mid": palette.color(QPalette.ColorRole.Mid),
        "dark": palette.color(QPalette.ColorRole.Dark),
        "shadow": palette.color(QPalette.ColorRole.Shadow),
        "text": palette.color(QPalette.ColorRole.WindowText),
        "base": palette.color(QPalette.ColorRole.Base),
        "highlight": palette.color(QPalette.ColorRole.Highlight),
        "disabled": palette.color(QPalette.ColorGroup.Disabled, QPalette.ColorRole.WindowText),
    }


def make_disabled_pixmap(pm: QPixmap) -> QPixmap:
    """Create a classic Mac OS 8/9 dimmed Platinum icon variant."""
    img = pm.toImage().convertToFormat(pm.toImage().Format.Format_ARGB32)
    for y in range(img.height()):
        for x in range(img.width()):
            c = img.pixelColor(x, y)
            if c.alpha() == 0:
                continue
            lum = int(0.299 * c.red() + 0.587 * c.green() + 0.114 * c.blue())
            muted = 128 + (lum - 128) // 2
            img.setPixelColor(x, y, QColor(muted, muted, muted, min(c.alpha(), 170)))
    return QPixmap.fromImage(img)


def make_nav_icon(name: str, size: int = 20) -> QPixmap:
    """Draw a crisp 1997 Netscape Navigator 4.0 style pixel icon."""
    pm = QPixmap(size, size)
    pm.fill(Qt.GlobalColor.transparent)
    p = QPainter(pm)
    p.setRenderHint(QPainter.RenderHint.Antialiasing, False)

    black = QColor("#000000")
    white = QColor("#ffffff")
    teal = QColor("#2f8f83")
    teal_light = QColor("#66ccaa")
    teal_dark = QColor("#18544d")
    blue = QColor("#3366aa")
    blue_light = QColor("#77aaee")
    gold = QColor("#f2c230")
    gold_dark = QColor("#996600")
    red = QColor("#cc2222")
    red_light = QColor("#ff6655")
    gray_light = QColor("#eeeeee")
    gray_mid = QColor("#999999")

    if name == "back":
        arrow = QPolygon([
            QPoint(2, 10), QPoint(9, 3), QPoint(9, 7),
            QPoint(17, 7), QPoint(17, 13), QPoint(9, 13), QPoint(9, 17),
        ])
        p.setPen(QPen(black, 1))
        p.setBrush(teal)
        p.drawPolygon(arrow)
        p.setPen(teal_light)
        p.drawLine(4, 9, 8, 5)
        p.drawLine(9, 8, 16, 8)
        p.setPen(teal_dark)
        p.drawLine(9, 12, 16, 12)

    elif name == "forward":
        arrow = QPolygon([
            QPoint(17, 10), QPoint(10, 3), QPoint(10, 7),
            QPoint(2, 7), QPoint(2, 13), QPoint(10, 13), QPoint(10, 17),
        ])
        p.setPen(QPen(black, 1))
        p.setBrush(teal)
        p.drawPolygon(arrow)
        p.setPen(teal_light)
        p.drawLine(3, 8, 10, 8)
        p.drawLine(11, 5, 15, 9)
        p.setPen(teal_dark)
        p.drawLine(3, 12, 10, 12)

    elif name == "reload":
        # Classic page + curved reload arrows
        p.setPen(black)
        p.setBrush(white)
        p.drawRect(5, 3, 10, 14)
        p.setPen(blue)
        p.drawArc(2, 2, 15, 15, 30 * 16, 140 * 16)
        p.drawArc(2, 2, 15, 15, 210 * 16, 140 * 16)
        # Top-right arrowhead
        p.setBrush(teal)
        p.setPen(black)
        p.drawPolygon(QPolygon([QPoint(15, 5), QPoint(18, 9), QPoint(12, 9)]))
        # Bottom-left arrowhead
        p.drawPolygon(QPolygon([QPoint(4, 14), QPoint(1, 10), QPoint(7, 10)]))

    elif name == "home":
        # Pitched roof house
        p.setPen(black)
        p.setBrush(red)
        p.drawRect(13, 3, 2, 4)  # Chimney
        roof = QPolygon([QPoint(10, 2), QPoint(2, 10), QPoint(18, 10)])
        p.setBrush(blue)
        p.drawPolygon(roof)
        p.setPen(blue_light)
        p.drawLine(10, 4, 4, 9)
        p.setPen(black)
        p.setBrush(gold)
        p.drawRect(4, 10, 12, 8)
        p.setBrush(teal_dark)
        p.drawRect(8, 12, 4, 6)
        p.setBrush(white)
        p.drawRect(5, 12, 2, 2)

    elif name == "search":
        # Flashlight shining over globe/page
        p.setPen(black)
        p.setBrush(gold)
        beam = QPolygon([QPoint(9, 9), QPoint(18, 14), QPoint(18, 18), QPoint(6, 18)])
        p.drawPolygon(beam)
        p.setBrush(gray_light)
        p.drawRect(2, 4, 8, 5)
        p.setBrush(blue)
        p.drawRect(2, 4, 5, 5)
        p.setPen(black)
        p.drawLine(10, 3, 12, 5)
        p.drawLine(10, 9, 12, 7)

    elif name == "guide":
        # Compass / Guide icon
        p.setPen(black)
        p.setBrush(blue_light)
        p.drawEllipse(2, 2, 16, 16)
        p.setBrush(white)
        p.drawEllipse(4, 4, 12, 12)
        p.setBrush(red)
        p.drawPolygon(QPolygon([QPoint(13, 6), QPoint(11, 11), QPoint(8, 9)]))
        p.setBrush(blue)
        p.drawPolygon(QPolygon([QPoint(6, 14), QPoint(11, 11), QPoint(8, 9)]))

    elif name == "print":
        # Desktop printer with paper
        p.setPen(black)
        p.setBrush(white)
        p.drawRect(5, 2, 10, 6)
        p.setPen(gray_mid)
        p.drawLine(7, 4, 13, 4)
        p.drawLine(7, 6, 11, 6)
        p.setPen(black)
        p.setBrush(gray_light)
        p.drawRect(2, 8, 16, 7)
        p.setBrush(gray_mid)
        p.drawRect(4, 11, 12, 3)
        p.setBrush(white)
        p.drawRect(5, 13, 10, 5)
        p.setPen(teal)
        p.drawPoint(15, 9)

    elif name in ("security_open", "security_locked"):
        locked = name == "security_locked"
        p.setPen(QPen(black, 1))
        if locked:
            p.drawArc(6, 2, 8, 9, 0, 180 * 16)
            p.drawLine(6, 6, 6, 9)
            p.drawLine(14, 6, 14, 9)
            p.setBrush(gold)
            p.drawRect(4, 9, 12, 9)
            p.setPen(gold_dark)
            p.drawLine(5, 17, 15, 17)
            p.setPen(black)
            p.drawRect(9, 12, 2, 3)
        else:
            p.drawArc(4, 2, 8, 8, 0, 180 * 16)
            p.drawLine(4, 6, 4, 10)
            p.setBrush(gray_light)
            p.drawRect(5, 9, 11, 9)
            p.setPen(gray_mid)
            p.drawLine(6, 17, 15, 17)
            p.setPen(black)
            p.drawRect(10, 12, 2, 3)

    elif name == "stop":
        oct_pts = QPolygon([
            QPoint(6, 2), QPoint(14, 2),
            QPoint(18, 6), QPoint(18, 14),
            QPoint(14, 18), QPoint(6, 18),
            QPoint(2, 14), QPoint(2, 6),
        ])
        p.setPen(black)
        p.setBrush(red)
        p.drawPolygon(oct_pts)
        p.setPen(white)
        # Inner white octagon accent + X/hand mark
        p.drawLine(7, 4, 13, 4)
        p.drawLine(4, 7, 4, 13)
        p.drawLine(7, 7, 13, 13)
        p.drawLine(13, 7, 7, 13)

    p.end()
    return pm


def make_mini_icon(name: str, size: int = 14) -> QPixmap:
    """Draw a 14x14 retro icon for Location, Personal, and Component toolbars."""
    pm = QPixmap(size, size)
    pm.fill(Qt.GlobalColor.transparent)
    p = QPainter(pm)
    p.setRenderHint(QPainter.RenderHint.Antialiasing, False)

    black = QColor("#000000")
    white = QColor("#ffffff")
    teal = QColor("#2f8f83")
    blue = QColor("#3366aa")
    gold = QColor("#f2c230")
    red = QColor("#cc3333")
    gray = QColor("#bbbbbb")

    if name == "bookmarks":
        p.setPen(black)
        p.setBrush(teal)
        p.drawRect(2, 1, 9, 11)
        p.setBrush(red)
        p.drawPolygon(QPolygon([QPoint(5, 2), QPoint(9, 2), QPoint(9, 10), QPoint(7, 8), QPoint(5, 10)]))
    elif name == "page":
        p.setPen(black)
        p.setBrush(white)
        p.drawRect(2, 1, 9, 12)
        p.setPen(blue)
        p.drawLine(4, 4, 9, 4)
        p.drawLine(4, 6, 9, 6)
        p.drawLine(4, 8, 8, 8)
    elif name == "home":
        p.setPen(black)
        p.setBrush(blue)
        p.drawPolygon(QPolygon([QPoint(7, 1), QPoint(1, 7), QPoint(13, 7)]))
        p.setBrush(gold)
        p.drawRect(3, 7, 8, 6)
    elif name == "search":
        p.setPen(black)
        p.setBrush(QColor("#aaddff"))
        p.drawEllipse(1, 1, 8, 8)
        p.drawLine(8, 8, 12, 12)
    elif name == "guide":
        p.setPen(black)
        p.setBrush(QColor("#88ccaa"))
        p.drawEllipse(1, 1, 11, 11)
        p.setBrush(red)
        p.drawPolygon(QPolygon([QPoint(9, 4), QPoint(7, 8), QPoint(5, 6)]))
    elif name == "folder":
        p.setPen(black)
        p.setBrush(gold)
        p.drawRect(1, 3, 5, 2)
        p.drawRect(1, 4, 11, 8)
    elif name == "navigator":
        # Ship's wheel
        p.setPen(black)
        p.setBrush(gold)
        p.drawEllipse(2, 2, 10, 10)
        p.setBrush(white)
        p.drawEllipse(5, 5, 4, 4)
        p.drawLine(7, 0, 7, 13)
        p.drawLine(0, 7, 13, 7)
    elif name == "mail":
        # Envelope
        p.setPen(black)
        p.setBrush(white)
        p.drawRect(1, 3, 12, 8)
        p.drawLine(1, 3, 7, 8)
        p.drawLine(13, 3, 7, 8)
    elif name == "collabra":
        # Overlapping message pages
        p.setPen(black)
        p.setBrush(gray)
        p.drawRect(4, 1, 8, 9)
        p.setBrush(white)
        p.drawRect(1, 4, 8, 9)
        p.setPen(blue)
        p.drawLine(3, 7, 7, 7)
        p.drawLine(3, 9, 7, 9)
    elif name == "composer":
        # Page + pencil
        p.setPen(black)
        p.setBrush(white)
        p.drawRect(2, 2, 8, 10)
        p.setPen(red)
        p.drawLine(12, 2, 6, 10)
        p.setPen(gold)
        p.drawLine(11, 2, 5, 10)
    elif name in ("security_open", "security_locked"):
        locked = name == "security_locked"
        p.setPen(black)
        if locked:
            p.drawArc(4, 1, 6, 6, 0, 180 * 16)
            p.drawLine(4, 4, 4, 6)
            p.drawLine(10, 4, 10, 6)
            p.setBrush(gold)
            p.drawRect(3, 6, 8, 6)
            p.drawPoint(7, 9)
        else:
            p.drawArc(3, 1, 6, 6, 0, 180 * 16)
            p.drawLine(3, 4, 3, 7)
            p.setBrush(QColor("#dddddd"))
            p.drawRect(4, 6, 8, 6)
            p.drawPoint(8, 9)

    p.end()
    return pm


def draw_platinum_bevel_button(
    painter: QPainter,
    rect: QRect,
    palette: QPalette,
    enabled: bool,
    down: bool,
    hovered: bool,
    radius: int = 3,
) -> None:
    """Paint a ZacOS 9 Platinum bevel button matching gtk.css & zacos9style."""
    c = platinum_colors(palette)
    is_dark = c["button"].lightness() < 128
    r = rect.adjusted(0, 0, -1, -1)

    if not enabled:
        fill = c["window"]
        border = c["dark"]
        painter.setPen(border)
        painter.setBrush(fill)
        painter.drawRoundedRect(r, radius, radius)
        return

    if down:
        fill = QColor("#555555") if is_dark else QColor("#999999")
        inset = QColor("#222222") if is_dark else QColor("#666666")
        painter.setPen(c["shadow"])
        painter.setBrush(fill)
        painter.drawRoundedRect(r, radius, radius)
        painter.setPen(inset)
        painter.drawLine(r.left() + 2, r.top() + 1, r.right() - 2, r.top() + 1)
        painter.drawLine(r.left() + 1, r.top() + 2, r.left() + 1, r.bottom() - 2)
        return

    if is_dark:
        top_col = c["light"] if hovered else c["midlight"]
        bot_col = c["button"] if hovered else c["mid"]
        hi_col = c["light"]
        lo_col = c["shadow"]
    else:
        top_col = QColor("#f4f4f4") if hovered else QColor("#eeeeee")
        bot_col = QColor("#d4d4d4") if hovered else QColor("#cccccc")
        hi_col = QColor("#ffffff")
        lo_col = QColor("#888888")

    grad = QLinearGradient(r.left(), r.top(), r.left(), r.bottom())
    grad.setColorAt(0.0, top_col)
    grad.setColorAt(1.0, bot_col)
    painter.setPen(c["shadow"])
    painter.setBrush(grad)
    painter.drawRoundedRect(r, radius, radius)

    # Inner Platinum 3D bevel highlight (top/left) & shadow (bottom/right)
    painter.setPen(hi_col)
    painter.drawLine(r.left() + 2, r.top() + 1, r.right() - 2, r.top() + 1)
    painter.drawLine(r.left() + 1, r.top() + 2, r.left() + 1, r.bottom() - 2)
    painter.setPen(lo_col)
    painter.drawLine(r.left() + 2, r.bottom() - 1, r.right() - 2, r.bottom() - 1)
    painter.drawLine(r.right() - 1, r.top() + 2, r.right() - 1, r.bottom() - 2)


class PlatinumNavButton(QPushButton):
    """Navigation toolbar button styled as a full-height ZacOS 9 Platinum bevel button."""

    def __init__(self, label: str, icon_name: str, parent=None):
        super().__init__(parent)
        self._label = label
        self._hovered = False
        self._icon_pm = make_nav_icon(icon_name, 20)
        self._disabled_pm = make_disabled_pixmap(self._icon_pm)
        self.setFixedSize(54, 44)
        self.setFocusPolicy(Qt.FocusPolicy.NoFocus)
        self.setFont(zacos_font("ZacOS 9 Views", 9))

    def set_icon_name(self, icon_name: str) -> None:
        self._icon_pm = make_nav_icon(icon_name, 20)
        self._disabled_pm = make_disabled_pixmap(self._icon_pm)
        self.update()

    def enterEvent(self, event) -> None:
        self._hovered = True
        self.update()
        super().enterEvent(event)

    def leaveEvent(self, event) -> None:
        self._hovered = False
        self.update()
        super().leaveEvent(event)

    def paintEvent(self, event) -> None:
        painter = QPainter(self)
        draw_platinum_bevel_button(
            painter,
            self.rect(),
            self.palette(),
            self.isEnabled(),
            self.isDown(),
            self._hovered,
            radius=3,
        )

        offset = 1 if self.isDown() else 0
        pm = self._icon_pm if self.isEnabled() else self._disabled_pm
        ix = (self.width() - pm.width()) // 2 + offset
        iy = 5 + offset
        painter.drawPixmap(ix, iy, pm)

        painter.setFont(self.font())
        group = QPalette.ColorGroup.Normal if self.isEnabled() else QPalette.ColorGroup.Disabled
        painter.setPen(self.palette().color(group, QPalette.ColorRole.ButtonText))
        text_rect = QRect(2 + offset, 26 + offset, self.width() - 4, 14)
        painter.drawText(text_rect, Qt.AlignmentFlag.AlignCenter, self._label)


class PlatinumCompactButton(QPushButton):
    """Compact Platinum button for Location and Personal toolbars."""

    def __init__(self, label: str, icon_name: str, has_arrow: bool = False, parent=None):
        super().__init__(parent)
        self._label = label
        self._icon_pm = make_mini_icon(icon_name, 14)
        self._has_arrow = has_arrow
        self.setFocusPolicy(Qt.FocusPolicy.NoFocus)
        self.setFont(zacos_font("ZacOS 9 Views", 9))
        self.setFixedHeight(22)
        fm = self.fontMetrics()
        w = 8 + self._icon_pm.width() + 4 + fm.horizontalAdvance(label) + (12 if has_arrow else 6)
        self.setFixedWidth(max(44, w))

    def paintEvent(self, event) -> None:
        painter = QPainter(self)
        opt = QStyleOptionButton()
        self.initStyleOption(opt)
        opt.text = ""
        opt.icon = QIcon()
        self.style().drawControl(QStyle.ControlElement.CE_PushButtonBevel, opt, painter, self)

        offset = 1 if self.isDown() else 0
        x = 5 + offset
        iy = (self.height() - self._icon_pm.height()) // 2 + offset
        painter.drawPixmap(x, iy, self._icon_pm)
        x += self._icon_pm.width() + 4

        painter.setFont(self.font())
        painter.setPen(self.palette().color(QPalette.ColorRole.ButtonText))
        text_w = self.width() - x - (12 if self._has_arrow else 4)
        painter.drawText(
            QRect(x, offset, text_w, self.height()),
            Qt.AlignmentFlag.AlignVCenter | Qt.AlignmentFlag.AlignLeft,
            self._label,
        )

        if self._has_arrow:
            ax = self.width() - 9 + offset
            ay = self.height() // 2 - 1 + offset
            painter.setBrush(self.palette().color(QPalette.ColorRole.ButtonText))
            painter.setPen(Qt.PenStyle.NoPen)
            painter.drawPolygon(QPolygon([QPoint(ax, ay), QPoint(ax + 5, ay), QPoint(ax + 2, ay + 3)]))


class PlatinumMiniIconButton(QPushButton):
    """Square Platinum mini-button for Security lock and Component Bar icons."""

    def __init__(self, icon_name: str, tooltip: str, parent=None):
        super().__init__(parent)
        self._icon_pm = make_mini_icon(icon_name, 14)
        self.setFixedSize(20, 20)
        self.setFocusPolicy(Qt.FocusPolicy.NoFocus)
        self.setToolTip(tooltip)

    def set_icon_name(self, icon_name: str) -> None:
        self._icon_pm = make_mini_icon(icon_name, 14)
        self.update()

    def paintEvent(self, event) -> None:
        painter = QPainter(self)
        opt = QStyleOptionButton()
        self.initStyleOption(opt)
        opt.text = ""
        opt.icon = QIcon()
        self.style().drawControl(QStyle.ControlElement.CE_PushButtonBevel, opt, painter, self)

        offset = 1 if self.isDown() else 0
        ix = (self.width() - self._icon_pm.width()) // 2 + offset
        iy = (self.height() - self._icon_pm.height()) // 2 + offset
        painter.drawPixmap(ix, iy, self._icon_pm)


class NetscapeThrobber(QPushButton):
    """Animated Netscape Navigator 4.0 'N' comet/horizon logo button."""

    def __init__(self, parent=None):
        super().__init__(parent)
        self.setFixedSize(44, 44)
        self.setFocusPolicy(Qt.FocusPolicy.NoFocus)
        self.setToolTip("Netscape Home")
        self._animating = False
        self._frame = 0
        self._timer = QTimer(self)
        self._timer.setInterval(75)
        self._timer.timeout.connect(self._advance)

    def set_loading(self, loading: bool) -> None:
        if loading == self._animating:
            return
        self._animating = loading
        if loading:
            self._timer.start()
        else:
            self._timer.stop()
            self._frame = 0
        self.update()

    def _advance(self) -> None:
        self._frame = (self._frame + 1) % 16
        self.update()

    def paintEvent(self, event) -> None:
        painter = QPainter(self)
        draw_platinum_bevel_button(
            painter,
            self.rect(),
            self.palette(),
            self.isEnabled(),
            self.isDown(),
            self.underMouse(),
            radius=3,
        )

        inner = self.rect().adjusted(4, 4, -4, -4)
        c = platinum_colors(self.palette())
        # Sunken frame inside Platinum button
        painter.setPen(c["shadow"])
        painter.drawRect(inner.adjusted(-1, -1, 0, 0))

        # Deep space -> teal horizon gradient
        grad = QLinearGradient(inner.left(), inner.top(), inner.left(), inner.bottom())
        grad.setColorAt(0.0, QColor("#040b24"))
        grad.setColorAt(0.62, QColor("#0b3a4a"))
        grad.setColorAt(1.0, QColor("#1e7874"))
        painter.fillRect(inner, grad)

        # Shooting stars / meteor animation when loading
        if self._animating:
            painter.save()
            painter.setClipRect(inner)
            stars = [(6, 7), (15, 5), (27, 9), (33, 14), (10, 17), (29, 20)]
            for idx, (sx, sy) in enumerate(stars):
                px = inner.left() + (sx + self._frame * (idx % 2 + 2)) % inner.width()
                py = inner.top() + (sy + self._frame) % (inner.height() - 10)
                painter.setPen(QColor("#aaffee"))
                painter.drawLine(px, py, px - 4, py + 2)
                painter.setPen(QColor("#ffffff"))
                painter.drawPoint(px, py)
            painter.restore()
        else:
            painter.setPen(QColor("#88bbbb"))
            for sx, sy in ((8, 9), (31, 8), (12, 16), (30, 18)):
                painter.drawPoint(inner.left() + sx, inner.top() + sy)

        # Curved planetary horizon at the bottom
        painter.setPen(Qt.PenStyle.NoPen)
        painter.setBrush(QColor("#114b4b"))
        painter.drawChord(inner.left() - 6, inner.bottom() - 10, inner.width() + 12, 22, 0, 180 * 16)
        painter.setPen(QColor("#66cccc"))
        painter.drawArc(inner.left() - 6, inner.bottom() - 10, inner.width() + 12, 22, 20 * 16, 140 * 16)

        # Classic white serif "N"
        offset = 1 if self.isDown() else 0
        n_font = QFont("Nimbus Roman")
        n_font.setPixelSize(27)
        n_font.setBold(True)
        painter.setFont(n_font)
        painter.setPen(QColor("#000000"))
        painter.drawText(inner.adjusted(1 + offset, 0 + offset, 1, -2), Qt.AlignmentFlag.AlignCenter, "N")
        painter.setPen(QColor("#ffffff"))
        painter.drawText(inner.adjusted(offset, -1 + offset, 0, -3), Qt.AlignmentFlag.AlignCenter, "N")


class ToolbarGrip(QWidget):
    """Mac OS 8 / Netscape 4.0 left collapse grip with disclosure triangle."""

    toggled = pyqtSignal()

    def __init__(self, tooltip: str, parent=None):
        super().__init__(parent)
        self._collapsed = False
        self.setFixedWidth(14)
        self.setCursor(Qt.CursorShape.PointingHandCursor)
        self.setToolTip(tooltip)

    def set_collapsed(self, collapsed: bool) -> None:
        self._collapsed = collapsed
        self.update()

    def mousePressEvent(self, event) -> None:
        if event.button() == Qt.MouseButton.LeftButton:
            self.toggled.emit()
        super().mousePressEvent(event)

    def paintEvent(self, event) -> None:
        p = QPainter(self)
        c = platinum_colors(self.palette())
        r = self.rect()

        # Platinum raised handle pill
        box = r.adjusted(2, 2, -2, -2)
        p.fillRect(box, c["button"])
        p.setPen(c["light"])
        p.drawLine(box.left(), box.top(), box.right(), box.top())
        p.drawLine(box.left(), box.top(), box.left(), box.bottom())
        p.setPen(c["dark"])
        p.drawLine(box.right(), box.top(), box.right(), box.bottom())
        p.drawLine(box.left(), box.bottom(), box.right(), box.bottom())

        # Disclosure triangle at top
        cx = box.center().x()
        ty = box.top() + 5
        p.setBrush(c["text"])
        p.setPen(Qt.PenStyle.NoPen)
        if self._collapsed:
            p.drawPolygon(QPolygon([QPoint(cx - 2, ty - 2), QPoint(cx + 2, ty + 1), QPoint(cx - 2, ty + 4)]))
        else:
            p.drawPolygon(QPolygon([QPoint(cx - 3, ty), QPoint(cx + 3, ty), QPoint(cx, ty + 4)]))

        # Ribbed vertical grip lines below triangle
        y = ty + 8
        while y < box.bottom() - 3:
            p.setPen(c["light"])
            p.drawPoint(cx - 1, y)
            p.setPen(c["dark"])
            p.drawPoint(cx, y + 1)
            y += 3


class CollapsedToolbarStrip(QWidget):
    """Slim Mac OS 8 left-aligned collapsed toolbar strip."""

    clicked = pyqtSignal()

    def __init__(self, title: str, parent=None):
        super().__init__(parent)
        self._title = title
        self.setFixedHeight(14)
        self.setCursor(Qt.CursorShape.PointingHandCursor)
        self.setToolTip(f"Expand {title}")
        self.setFont(zacos_font("ZacOS 9 Views", 9))

    def mousePressEvent(self, event) -> None:
        if event.button() == Qt.MouseButton.LeftButton:
            self.clicked.emit()
        super().mousePressEvent(event)

    def paintEvent(self, event) -> None:
        p = QPainter(self)
        c = platinum_colors(self.palette())
        p.fillRect(self.rect(), c["window"])
        # Right-pointing disclosure triangle at left
        p.setBrush(c["text"])
        p.setPen(Qt.PenStyle.NoPen)
        cy = self.height() // 2
        p.drawPolygon(QPolygon([QPoint(6, cy - 3), QPoint(10, cy), QPoint(6, cy + 3)]))
        p.setFont(self.font())
        p.setPen(c["text"])
        p.drawText(QRect(15, 0, self.width() - 18, self.height()), Qt.AlignmentFlag.AlignVCenter | Qt.AlignmentFlag.AlignLeft, self._title)


class CollapsibleToolbar(QWidget):
    """Platinum toolbar container that collapses into a slim Mac OS 8 strip."""

    visibility_toggled = pyqtSignal(bool)

    def __init__(self, title: str, body: QWidget, parent=None):
        super().__init__(parent)
        self._title = title
        self._body = body
        self._collapsed = False

        outer = QVBoxLayout(self)
        outer.setContentsMargins(0, 0, 0, 0)
        outer.setSpacing(0)

        # Expanded row
        self._expanded_row = QWidget()
        row_lay = QHBoxLayout(self._expanded_row)
        row_lay.setContentsMargins(2, 2, 4, 2)
        row_lay.setSpacing(4)
        self._grip = ToolbarGrip(f"Collapse {title}")
        self._grip.toggled.connect(self.toggle_collapsed)
        row_lay.addWidget(self._grip)
        row_lay.addWidget(self._body, 1)

        # Collapsed strip
        self._collapsed_bar = CollapsedToolbarStrip(title)
        self._collapsed_bar.clicked.connect(self.toggle_collapsed)
        self._collapsed_bar.hide()

        outer.addWidget(self._expanded_row)
        outer.addWidget(self._collapsed_bar)

    def toggle_collapsed(self) -> None:
        self.set_collapsed(not self._collapsed)

    def set_collapsed(self, collapsed: bool) -> None:
        self._collapsed = collapsed
        self._grip.set_collapsed(collapsed)
        self._expanded_row.setVisible(not collapsed)
        self._collapsed_bar.setVisible(collapsed)

    def paintEvent(self, event) -> None:
        p = QPainter(self)
        c = platinum_colors(self.palette())
        p.fillRect(self.rect(), c["window"])
        # Classic Platinum 1px top highlight and 1px bottom bevel shadow
        p.setPen(c["light"])
        p.drawLine(0, 0, self.width() - 1, 0)
        p.setPen(c["dark"])
        p.drawLine(0, self.height() - 1, self.width() - 1, self.height() - 1)


class PlatinumViewportFrame(QFrame):
    """Inset Platinum well framing the web rendering area."""

    def __init__(self, child: QWidget, parent=None):
        super().__init__(parent)
        lay = QVBoxLayout(self)
        lay.setContentsMargins(2, 2, 2, 2)
        lay.setSpacing(0)
        lay.addWidget(child)

    def paintEvent(self, event) -> None:
        p = QPainter(self)
        c = platinum_colors(self.palette())
        w, h = self.width(), self.height()
        p.fillRect(self.rect(), c["window"])
        # Outer inset
        p.setPen(c["dark"])
        p.drawLine(0, 0, w - 1, 0)
        p.drawLine(0, 0, 0, h - 1)
        p.setPen(c["light"])
        p.drawLine(0, h - 1, w - 1, h - 1)
        p.drawLine(w - 1, 0, w - 1, h - 1)
        # Inner crisp 1px black frame
        p.setPen(c["shadow"])
        p.drawRect(1, 1, w - 3, h - 3)


def retro_html_page(title: str, subtitle: str, body_html: str) -> str:
    """Render an authentic 1997 Netscape gray-canvas HTML page."""
    return f"""<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{html.escape(title)}</title>
<style>
  body {{
    background-color: #c0c0c0;
    color: #000000;
    font-family: 'Nimbus Roman', 'Liberation Serif', Georgia, serif;
    margin: 24px 36px;
  }}
  .banner {{
    background: linear-gradient(to right, #001838, #0f5656);
    color: #ffffff;
    border: 2px outset #eeeeee;
    padding: 12px 16px;
    margin-bottom: 16px;
  }}
  .banner h1 {{
    margin: 0;
    font-size: 22px;
    letter-spacing: 0.5px;
  }}
  .banner p {{
    margin: 4px 0 0 0;
    color: #cceeee;
    font-size: 13px;
  }}
  .box {{
    background-color: #dddddd;
    border: 2px groove #ffffff;
    padding: 12px 16px;
    margin-bottom: 14px;
  }}
  a {{ color: #0000ee; }}
  a:visited {{ color: #551a8b; }}
  hr {{ border: 1px inset #888888; }}
  code {{ background: #eeeeee; padding: 1px 4px; border: 1px solid #888888; }}
</style>
</head>
<body>
  <div class="banner">
    <h1>{html.escape(title)}</h1>
    <p>{html.escape(subtitle)}</p>
  </div>
  <div class="box">
    {body_html}
  </div>
  <hr>
  <p style="font-size: 12px; color: #333333;">
    Netscape Communicator 4.0 &mdash; ZacOS 9 Platinum Edition
  </p>
</body>
</html>"""


class AboutDialog(QDialog):
    """Classic Mac OS 8 Netscape Navigator 4.0 About Box."""

    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle("About Netscape")
        self.setFixedSize(410, 250)
        lay = QVBoxLayout(self)
        lay.setContentsMargins(14, 14, 14, 12)
        lay.setSpacing(10)

        top = QHBoxLayout()
        top.setSpacing(12)
        badge = NetscapeThrobber()
        badge.setEnabled(False)
        badge.setFixedSize(56, 56)
        top.addWidget(badge, 0, Qt.AlignmentFlag.AlignTop)

        info = QVBoxLayout()
        title = QLabel("Netscape Navigator\u2122 4.08")
        title.setFont(zacos_font("ZacOS 9 System", 12, bold=True))
        sub = QLabel(
            "Netscape Communicator 4.0 (ZacOS 9 / Platinum)\n\n"
            "Built with PyQt6 WebEngine following ZacOS 9\n"
            "Platinum controls, pixel fonts, and classic\n"
            "Mac OS 8 Navigator chrome."
        )
        sub.setFont(zacos_font("ZacOS 9 Views", 9))
        sub.setWordWrap(True)
        info.addWidget(title)
        info.addWidget(sub)
        top.addLayout(info, 1)
        lay.addLayout(top)

        line = QFrame()
        line.setFrameShape(QFrame.Shape.HLine)
        lay.addWidget(line)

        buttons = QDialogButtonBox(QDialogButtonBox.StandardButton.Ok)
        buttons.accepted.connect(self.accept)
        lay.addWidget(buttons)


class BookmarksDialog(QDialog):
    """Simple Platinum Bookmarks Manager."""

    open_requested = pyqtSignal(str)

    def __init__(self, bookmarks: list[tuple[str, str]], parent=None):
        super().__init__(parent)
        self._bookmarks = bookmarks
        self.setWindowTitle("Bookmarks")
        self.resize(440, 300)
        lay = QVBoxLayout(self)
        lay.setContentsMargins(10, 10, 10, 10)
        lay.setSpacing(8)

        self._list = QListWidget()
        self._refresh()
        self._list.itemDoubleClicked.connect(self._open_selected)
        lay.addWidget(self._list, 1)

        row = QHBoxLayout()
        open_btn = QPushButton("Open")
        open_btn.clicked.connect(self._open_selected)
        remove_btn = QPushButton("Delete")
        remove_btn.clicked.connect(self._delete_selected)
        close_btn = QPushButton("Close")
        close_btn.setDefault(True)
        close_btn.clicked.connect(self.accept)
        row.addWidget(open_btn)
        row.addWidget(remove_btn)
        row.addStretch(1)
        row.addWidget(close_btn)
        lay.addLayout(row)

    def _refresh(self) -> None:
        self._list.clear()
        for title, url in self._bookmarks:
            item = QListWidgetItem(f"{title}  \u2014  {url}")
            item.setData(Qt.ItemDataRole.UserRole, url)
            self._list.addItem(item)

    def _open_selected(self) -> None:
        item = self._list.currentItem()
        if item:
            self.open_requested.emit(item.data(Qt.ItemDataRole.UserRole))
            self.accept()

    def _delete_selected(self) -> None:
        idx = self._list.currentRow()
        if 0 <= idx < len(self._bookmarks):
            self._bookmarks.pop(idx)
            self._refresh()


class PreferencesDialog(QDialog):
    """Netscape Navigator 4.0 Preferences dialog."""

    def __init__(self, home_url: str, autoload_images: bool, parent=None):
        super().__init__(parent)
        self.setWindowTitle("Preferences")
        self.resize(420, 180)
        lay = QVBoxLayout(self)
        lay.setContentsMargins(12, 12, 12, 12)
        lay.setSpacing(10)

        lbl = QLabel("Navigator Home Page Location:")
        lay.addWidget(lbl)
        self.home_edit = QLineEdit(home_url)
        lay.addWidget(self.home_edit)

        self.images_chk = QCheckBox("Automatically load images")
        self.images_chk.setChecked(autoload_images)
        lay.addWidget(self.images_chk)

        lay.addStretch(1)
        buttons = QDialogButtonBox(
            QDialogButtonBox.StandardButton.Ok | QDialogButtonBox.StandardButton.Cancel
        )
        buttons.accepted.connect(self.accept)
        buttons.rejected.connect(self.reject)
        lay.addWidget(buttons)


class NetscapeZacBrowser(QMainWindow):
    """Netscape Navigator 4.0 for Mac OS 8, styled natively for ZacOS 9."""

    def __init__(self):
        super().__init__()
        self.setWindowTitle("Netscape")
        self.resize(1024, 768)

        self._home_url = DEFAULT_HOME_URL
        self._bookmarks: list[tuple[str, str]] = list(DEFAULT_BOOKMARKS)
        self._visited_urls: list[str] = [url for _, url in DEFAULT_BOOKMARKS]
        self._last_search = ""
        self._current_url_str = DEFAULT_HOME_URL
        self._showing_builtin = False
        self._is_loading = False
        self._open_windows: list["NetscapeZacBrowser"] = []

        self.browser = QWebEngineView()
        self.browser.page().setBackgroundColor(QColor("#dddddd"))

        self._build_menus()
        self._build_toolbars()
        self._build_central_area()
        self._build_status_bar()
        self._connect_browser_signals()

        self.navigate_to(self._home_url)

    # ------------------------------------------------------------------ Menus
    def _build_menus(self) -> None:
        mb = self.menuBar()

        # File
        file_menu = mb.addMenu("File")
        self._add_action(file_menu, "New Navigator Window", self._new_window, QKeySequence.StandardKey.New)
        self._add_action(file_menu, "Open Page...", self._open_location_dialog, QKeySequence.StandardKey.Open)
        self._add_action(file_menu, "Save As...", self._save_page, QKeySequence.StandardKey.Save)
        file_menu.addSeparator()
        self._add_action(file_menu, "Print...", self._print_page, QKeySequence.StandardKey.Print)
        file_menu.addSeparator()
        self._add_action(file_menu, "Close", self.close, QKeySequence.StandardKey.Close)
        self._add_action(file_menu, "Quit", QApplication.instance().quit, QKeySequence.StandardKey.Quit)

        # Edit
        edit_menu = mb.addMenu("Edit")
        page = self.browser.page()
        self._add_action(edit_menu, "Undo", lambda: page.triggerAction(page.WebAction.Undo), QKeySequence.StandardKey.Undo)
        edit_menu.addSeparator()
        self._add_action(edit_menu, "Cut", lambda: page.triggerAction(page.WebAction.Cut), QKeySequence.StandardKey.Cut)
        self._add_action(edit_menu, "Copy", lambda: page.triggerAction(page.WebAction.Copy), QKeySequence.StandardKey.Copy)
        self._add_action(edit_menu, "Paste", lambda: page.triggerAction(page.WebAction.Paste), QKeySequence.StandardKey.Paste)
        self._add_action(edit_menu, "Select All", lambda: page.triggerAction(page.WebAction.SelectAll), QKeySequence.StandardKey.SelectAll)
        edit_menu.addSeparator()
        self._add_action(edit_menu, "Find in Page...", self._show_find_bar, QKeySequence.StandardKey.Find)
        self._add_action(edit_menu, "Find Again", self._find_next, QKeySequence("Ctrl+G"))
        edit_menu.addSeparator()
        self._add_action(edit_menu, "Preferences...", self._open_preferences)

        # View
        view_menu = mb.addMenu("View")
        self._show_hide_menu = view_menu.addMenu("Show/Hide")
        self._act_show_nav = self._add_checkable(self._show_hide_menu, "Navigation Toolbar", True)
        self._act_show_loc = self._add_checkable(self._show_hide_menu, "Location Toolbar", True)
        self._act_show_pers = self._add_checkable(self._show_hide_menu, "Personal Toolbar", True)
        self._act_show_status = self._add_checkable(self._show_hide_menu, "Status Bar", True)
        view_menu.addSeparator()
        self._act_stop_menu = self._add_action(view_menu, "Stop", self.browser.stop, QKeySequence("Esc"))
        self._add_action(view_menu, "Reload", self.browser.reload, QKeySequence("Ctrl+R"))
        view_menu.addSeparator()
        self._add_action(view_menu, "Page Source", self._view_page_source, QKeySequence("Ctrl+U"))
        self._add_action(view_menu, "Page Info", self._show_page_info, QKeySequence("Ctrl+I"))

        # Go
        self._go_menu = mb.addMenu("Go")
        self._go_menu.aboutToShow.connect(self._populate_go_menu)
        self._populate_go_menu()

        # Communicator
        comm_menu = mb.addMenu("Communicator")
        self._add_action(comm_menu, "Navigator", lambda: self.navigate_to("about:netscape"), QKeySequence("Ctrl+1"))
        self._add_action(comm_menu, "Messenger Mailbox", lambda: self.navigate_to("about:mail"), QKeySequence("Ctrl+2"))
        self._add_action(comm_menu, "Collabra Discussions", lambda: self.navigate_to("about:news"), QKeySequence("Ctrl+3"))
        self._add_action(comm_menu, "Page Composer", lambda: self.navigate_to("about:composer"), QKeySequence("Ctrl+4"))
        comm_menu.addSeparator()
        self._bookmarks_menu = comm_menu.addMenu("Bookmarks")
        self._bookmarks_menu.aboutToShow.connect(self._populate_bookmarks_menu)
        self._populate_bookmarks_menu()
        self._add_action(comm_menu, "History", self._show_history_dialog, QKeySequence("Ctrl+H"))
        self._add_action(comm_menu, "Security Info", self._show_page_info)

        # Help
        help_menu = mb.addMenu("Help")
        self._add_action(help_menu, "Help Contents", lambda: self.navigate_to("about:help"), QKeySequence.StandardKey.HelpContents)
        help_menu.addSeparator()
        self._add_action(help_menu, "About Netscape...", self._show_about)

    def _add_action(self, menu: QMenu, text: str, slot, shortcut=None) -> QAction:
        act = QAction(text, self)
        if shortcut is not None:
            act.setShortcut(shortcut)
        act.triggered.connect(slot)
        menu.addAction(act)
        return act

    def _add_checkable(self, menu: QMenu, text: str, checked: bool) -> QAction:
        act = QAction(text, self)
        act.setCheckable(True)
        act.setChecked(checked)
        menu.addAction(act)
        return act

    def _populate_go_menu(self) -> None:
        self._go_menu.clear()
        back_act = self._add_action(self._go_menu, "Back", self.browser.back, QKeySequence("Alt+Left"))
        back_act.setEnabled(self.browser.history().canGoBack())
        fwd_act = self._add_action(self._go_menu, "Forward", self.browser.forward, QKeySequence("Alt+Right"))
        fwd_act.setEnabled(self.browser.history().canGoForward())
        self._add_action(self._go_menu, "Home", lambda: self.navigate_to(self._home_url), QKeySequence("Alt+Home"))
        self._go_menu.addSeparator()
        self._add_action(self._go_menu, "History...", self._show_history_dialog, QKeySequence("Ctrl+H"))
        items = self.browser.history().items()
        if items:
            self._go_menu.addSeparator()
            cur = self.browser.history().currentItemIndex()
            for idx, item in enumerate(reversed(items[-10:])):
                real_idx = len(items) - 1 - idx
                label = item.title() or item.url().toString()
                if len(label) > 42:
                    label = label[:39] + "..."
                act = QAction(label, self)
                act.setCheckable(True)
                act.setChecked(real_idx == cur)
                act.triggered.connect(lambda _=False, i=real_idx: self.browser.history().goToItem(self.browser.history().items()[i]))
                self._go_menu.addAction(act)

    def _populate_bookmarks_menu(self, target_menu: QMenu | None = None) -> None:
        menu = target_menu or self._bookmarks_menu
        menu.clear()
        self._add_action(menu, "Add Bookmark", self._add_current_bookmark, QKeySequence("Ctrl+D"))
        self._add_action(menu, "Edit Bookmarks...", self._manage_bookmarks, QKeySequence("Ctrl+B"))
        menu.addSeparator()
        for title, url in self._bookmarks:
            act = QAction(title, self)
            act.setToolTip(url)
            act.triggered.connect(lambda _=False, u=url: self.navigate_to(u))
            menu.addAction(act)

    # --------------------------------------------------------------- Toolbars
    def _build_toolbars(self) -> None:
        # 1. Navigation Toolbar
        nav_body = QWidget()
        nav_lay = QHBoxLayout(nav_body)
        nav_lay.setContentsMargins(0, 0, 0, 0)
        nav_lay.setSpacing(2)

        self.btn_back = PlatinumNavButton("Back", "back")
        self.btn_back.setToolTip("Go back one page")
        self.btn_back.clicked.connect(self.browser.back)

        self.btn_forward = PlatinumNavButton("Forward", "forward")
        self.btn_forward.setToolTip("Go forward one page")
        self.btn_forward.clicked.connect(self.browser.forward)

        self.btn_reload = PlatinumNavButton("Reload", "reload")
        self.btn_reload.setToolTip("Reload current page")
        self.btn_reload.clicked.connect(self.browser.reload)

        self.btn_home = PlatinumNavButton("Home", "home")
        self.btn_home.setToolTip("Go to Home page")
        self.btn_home.clicked.connect(lambda: self.navigate_to(self._home_url))

        self.btn_search = PlatinumNavButton("Search", "search")
        self.btn_search.setToolTip("Search the Internet")
        self.btn_search.clicked.connect(lambda: self.navigate_to(DEFAULT_SEARCH_URL))

        self.btn_guide = PlatinumNavButton("Guide", "guide")
        self.btn_guide.setToolTip("Open Web Guide")
        self.btn_guide.clicked.connect(lambda: self.navigate_to(DEFAULT_GUIDE_URL))

        self.btn_print = PlatinumNavButton("Print", "print")
        self.btn_print.setToolTip("Print this page")
        self.btn_print.clicked.connect(self._print_page)

        self.btn_security = PlatinumNavButton("Security", "security_open")
        self.btn_security.setToolTip("Show security information")
        self.btn_security.clicked.connect(self._show_page_info)

        self.btn_stop = PlatinumNavButton("Stop", "stop")
        self.btn_stop.setToolTip("Stop loading")
        self.btn_stop.clicked.connect(self.browser.stop)

        for btn in (
            self.btn_back,
            self.btn_forward,
            self.btn_reload,
            self.btn_home,
            self.btn_search,
            self.btn_guide,
            self.btn_print,
            self.btn_security,
            self.btn_stop,
        ):
            nav_lay.addWidget(btn)

        nav_lay.addStretch(1)

        self.throbber = NetscapeThrobber()
        self.throbber.clicked.connect(lambda: self.navigate_to("about:netscape"))
        nav_lay.addWidget(self.throbber)

        self.nav_toolbar = CollapsibleToolbar("Navigation Toolbar", nav_body)

        # 2. Location Toolbar
        loc_body = QWidget()
        loc_lay = QHBoxLayout(loc_body)
        loc_lay.setContentsMargins(0, 1, 0, 1)
        loc_lay.setSpacing(6)

        self.btn_bookmarks = PlatinumCompactButton("Bookmarks", "bookmarks", has_arrow=True)
        self.btn_bookmarks.clicked.connect(self._show_bookmarks_popup)
        loc_lay.addWidget(self.btn_bookmarks)

        self.location_label = QLabel("Netsite:")
        self.location_label.setFont(zacos_font("ZacOS 9 System", 12))
        loc_lay.addWidget(self.location_label)

        self.page_proxy = PlatinumMiniIconButton("page", "Current page URL (click to bookmark)")
        self.page_proxy.clicked.connect(self._add_current_bookmark)
        loc_lay.addWidget(self.page_proxy)

        self.url_combo = QComboBox()
        self.url_combo.setEditable(True)
        self.url_combo.setInsertPolicy(QComboBox.InsertPolicy.NoInsert)
        self.url_combo.setSizePolicy(QSizePolicy.Policy.Expanding, QSizePolicy.Policy.Fixed)
        self.url_combo.setFixedHeight(22)
        self.url_combo.setFont(zacos_font("ZacOS 9 System", 12))
        for url in self._visited_urls:
            self.url_combo.addItem(url)
        self.url_combo.lineEdit().returnPressed.connect(self._navigate_from_bar)
        self.url_combo.activated.connect(self._navigate_from_combo)
        loc_lay.addWidget(self.url_combo, 1)

        self.loc_toolbar = CollapsibleToolbar("Location Toolbar", loc_body)

        # 3. Personal Toolbar
        pers_body = QWidget()
        self._pers_lay = QHBoxLayout(pers_body)
        self._pers_lay.setContentsMargins(0, 1, 0, 1)
        self._pers_lay.setSpacing(4)

        for label, url, icon_name in PERSONAL_TOOLBAR_LINKS:
            btn = PlatinumCompactButton(label, icon_name)
            btn.setToolTip(url)
            btn.clicked.connect(lambda _=False, u=url: self.navigate_to(u))
            self._pers_lay.addWidget(btn)

        self.btn_directory = PlatinumCompactButton("Internet", "folder", has_arrow=True)
        self.btn_directory.setToolTip("Classic Web Directory")
        self.btn_directory.clicked.connect(self._show_directory_popup)
        self._pers_lay.addWidget(self.btn_directory)

        self._pers_lay.addStretch(1)
        self.pers_toolbar = CollapsibleToolbar("Personal Toolbar", pers_body)

        # Wire View -> Show/Hide actions
        self._act_show_nav.toggled.connect(self.nav_toolbar.setVisible)
        self._act_show_loc.toggled.connect(self.loc_toolbar.setVisible)
        self._act_show_pers.toggled.connect(self.pers_toolbar.setVisible)

    # ----------------------------------------------------------- Central Area
    def _build_central_area(self) -> None:
        central = QWidget()
        root = QVBoxLayout(central)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        root.addWidget(self.nav_toolbar)
        root.addWidget(self.loc_toolbar)
        root.addWidget(self.pers_toolbar)

        # Compact inline Find bar (hidden until Ctrl+F)
        self._find_bar = QWidget()
        find_lay = QHBoxLayout(self._find_bar)
        find_lay.setContentsMargins(6, 3, 6, 3)
        find_lay.setSpacing(6)
        find_lay.addWidget(QLabel("Find:"))
        self._find_input = QLineEdit()
        self._find_input.setFixedWidth(220)
        self._find_input.returnPressed.connect(self._find_next)
        find_lay.addWidget(self._find_input)
        find_next_btn = QPushButton("Find Next")
        find_next_btn.clicked.connect(self._find_next)
        find_close_btn = QPushButton("Done")
        find_close_btn.clicked.connect(self._hide_find_bar)
        find_lay.addWidget(find_next_btn)
        find_lay.addWidget(find_close_btn)
        find_lay.addStretch(1)
        self._find_bar.hide()

        self._viewport_frame = PlatinumViewportFrame(self.browser)
        root.addWidget(self._viewport_frame, 1)
        root.addWidget(self._find_bar)

        self.setCentralWidget(central)

    # ------------------------------------------------------------- Status Bar
    def _build_status_bar(self) -> None:
        self.status = QStatusBar()
        self.status.setSizeGripEnabled(True)
        self.status.setFont(zacos_font("ZacOS 9 Views", 9))
        self.setStatusBar(self.status)

        self.status_security = PlatinumMiniIconButton("security_open", "Security Info")
        self.status_security.clicked.connect(self._show_page_info)
        self.status.addWidget(self.status_security)

        self.progress_bar = QProgressBar()
        self.progress_bar.setRange(0, 100)
        self.progress_bar.setValue(0)
        self.progress_bar.setTextVisible(False)
        self.progress_bar.setFixedSize(110, 14)
        self.status.addWidget(self.progress_bar)

        self.status_label = QLabel("Document: Done")
        self.status_label.setFont(zacos_font("ZacOS 9 Views", 9))
        self.status.addWidget(self.status_label, 1)

        # Docked Netscape Communicator Component Bar on the bottom right
        comp_box = QWidget()
        comp_lay = QHBoxLayout(comp_box)
        comp_lay.setContentsMargins(2, 0, 2, 0)
        comp_lay.setSpacing(2)
        for icon_name, tip, target in (
            ("navigator", "Navigator (Ctrl+1)", "about:netscape"),
            ("mail", "Messenger Mailbox (Ctrl+2)", "about:mail"),
            ("collabra", "Collabra Discussions (Ctrl+3)", "about:news"),
            ("composer", "Page Composer (Ctrl+4)", "about:composer"),
        ):
            b = PlatinumMiniIconButton(icon_name, tip)
            b.clicked.connect(lambda _=False, t=target: self.navigate_to(t))
            comp_lay.addWidget(b)
        self.status.addPermanentWidget(comp_box)

        self._act_show_status.toggled.connect(self.status.setVisible)
        self._update_nav_states()

    # -------------------------------------------------------- Browser Signals
    def _connect_browser_signals(self) -> None:
        self.browser.urlChanged.connect(self._on_url_changed)
        self.browser.titleChanged.connect(self._on_title_changed)
        self.browser.loadStarted.connect(self._on_load_started)
        self.browser.loadProgress.connect(self._on_load_progress)
        self.browser.loadFinished.connect(self._on_load_finished)
        self.browser.page().linkHovered.connect(self._on_link_hovered)

    def _on_url_changed(self, qurl: QUrl) -> None:
        url_str = qurl.toString()
        if self._showing_builtin and url_str in ("https://localhost/", "about:blank"):
            url_str = self._current_url_str
        elif url_str.startswith("data:") or url_str == "about:blank":
            url_str = self._current_url_str
        else:
            self._showing_builtin = False
            self._current_url_str = url_str

        self.url_combo.blockSignals(True)
        self.url_combo.setEditText(url_str)
        if url_str and url_str not in self._visited_urls:
            self._visited_urls.insert(0, url_str)
            self.url_combo.insertItem(0, url_str)
        self.url_combo.blockSignals(False)

        is_web = url_str.startswith(("http://", "https://"))
        self.location_label.setText("Netsite:" if is_web else "Location:")
        is_https = url_str.startswith("https://")
        sec_icon = "security_locked" if is_https else "security_open"
        self.btn_security.set_icon_name(sec_icon)
        self.status_security.set_icon_name(sec_icon)
        self._update_nav_states()

    def _on_title_changed(self, title: str) -> None:
        if title:
            self.setWindowTitle(f"Netscape: {title}")
        else:
            self.setWindowTitle("Netscape")

    def _on_load_started(self) -> None:
        self._is_loading = True
        self.throbber.set_loading(True)
        self.progress_bar.setValue(5)
        self.status_label.setText(f"Connecting to {self._current_url_str}...")
        self._update_nav_states()

    def _on_load_progress(self, progress: int) -> None:
        self.progress_bar.setValue(progress)
        if progress < 100:
            self.status_label.setText(f"Loading: {progress}%")
        else:
            self.status_label.setText("Document: Done")

    def _on_load_finished(self, ok: bool) -> None:
        self._is_loading = False
        self.throbber.set_loading(False)
        self.progress_bar.setValue(0)
        self.status_label.setText("Document: Done" if ok else "Transfer interrupted")
        self._update_nav_states()

    def _on_link_hovered(self, url: str) -> None:
        if url:
            self.status_label.setText(url)
        elif not self._is_loading:
            self.status_label.setText("Document: Done")

    def _update_nav_states(self) -> None:
        hist = self.browser.history()
        self.btn_back.setEnabled(hist.canGoBack())
        self.btn_forward.setEnabled(hist.canGoForward())
        self.btn_stop.setEnabled(self._is_loading)
        self._act_stop_menu.setEnabled(self._is_loading)

    # ------------------------------------------------------------- Navigation
    def navigate_to(self, url: str) -> None:
        raw = url.strip()
        if not raw:
            return
        if raw.startswith("about:"):
            self._load_builtin_about(raw)
            return
        if not raw.startswith(("http://", "https://", "file://")):
            raw = "https://" + raw
        self._showing_builtin = False
        self._current_url_str = raw
        self.browser.setUrl(QUrl(raw))

    def _navigate_from_bar(self) -> None:
        self.navigate_to(self.url_combo.currentText())

    def _navigate_from_combo(self, index: int) -> None:
        text = self.url_combo.itemText(index)
        if text:
            self.navigate_to(text)

    def _load_builtin_about(self, about_url: str) -> None:
        self._showing_builtin = True
        self._current_url_str = about_url
        self.url_combo.setEditText(about_url)
        self.location_label.setText("Location:")
        self.btn_security.set_icon_name("security_open")
        self.status_security.set_icon_name("security_open")

        pages = {
            "about:netscape": (
                "Welcome to Netscape Navigator 4.0",
                "Communicator Platinum Edition on ZacOS 9",
                "<p>Welcome aboard <b>Netscape Navigator 4.0</b> running on <b>ZacOS 9</b>.</p>"
                "<p>Quick links to explore the classic &amp; text-friendly Web:</p>"
                "<ul>"
                "<li><a href='https://frogfind.com'>FrogFind!</a> &mdash; Classic search engine for retro browsers</li>"
                "<li><a href='https://wiby.me'>Wiby</a> &mdash; Search engine for classic web pages</li>"
                "<li><a href='http://68k.news'>68k.news</a> &mdash; Fast headlines formatted for classic Macs</li>"
                "<li><a href='https://theoldnet.com'>The Old Net</a> &mdash; Browse snapshots of the 1990s web</li>"
                "</ul>",
            ),
            "about:mail": (
                "Netscape Messenger",
                "Communicator Electronic Mail Client",
                "<p>Netscape Messenger Mailbox is part of the Communicator 4.0 suite.</p>"
                "<p>Use <b>Communicator &rsaquo; Navigator</b> or <code>Ctrl+1</code> to return to web browsing.</p>",
            ),
            "about:news": (
                "Netscape Collabra",
                "Usenet &amp; Discussion Groups",
                "<p>Collabra Discussions lets you read newsgroups and team forums in Netscape Communicator 4.0.</p>"
                "<p>No NNTP news server is currently configured in this session.</p>",
            ),
            "about:composer": (
                "Netscape Composer",
                "WYSIWYG HTML Page Editor",
                "<p>Netscape Composer is the built-in HTML editor of Netscape Communicator 4.0.</p>"
                "<p>Use <b>View &rsaquo; Page Source</b> (<code>Ctrl+U</code>) while browsing to inspect page HTML.</p>",
            ),
            "about:help": (
                "Netscape Navigator Help",
                "Keyboard Shortcuts &amp; Toolbar Guide",
                "<ul>"
                "<li><b>Collapse Toolbars:</b> Click the ribbed grip on the left of any toolbar.</li>"
                "<li><b>Open Page:</b> <code>Ctrl+O</code></li>"
                "<li><b>Find in Page:</b> <code>Ctrl+F</code> / Find Again: <code>Ctrl+G</code></li>"
                "<li><b>Add Bookmark:</b> <code>Ctrl+D</code> / Manage: <code>Ctrl+B</code></li>"
                "<li><b>Reload / Stop:</b> <code>Ctrl+R</code> / <code>Esc</code></li>"
                "<li><b>Page Source / Info:</b> <code>Ctrl+U</code> / <code>Ctrl+I</code></li>"
                "</ul>",
            ),
        }
        title, subtitle, body = pages.get(
            about_url,
            ("About Netscape", about_url, "<p>Unknown built-in Navigator location.</p>"),
        )
        self.setWindowTitle(f"Netscape: {title}")
        self.browser.setHtml(retro_html_page(title, subtitle, body), QUrl("https://localhost/"))

    # -------------------------------------------------------- Popups & Dialogs
    def _show_bookmarks_popup(self) -> None:
        menu = QMenu(self)
        self._populate_bookmarks_menu(menu)
        pos = self.btn_bookmarks.mapToGlobal(QPoint(0, self.btn_bookmarks.height()))
        menu.exec(pos)

    def _show_directory_popup(self) -> None:
        menu = QMenu(self)
        for group_name, entries in DIRECTORY_LINKS:
            sub = menu.addMenu(group_name)
            for label, url in entries:
                act = QAction(label, self)
                act.triggered.connect(lambda _=False, u=url: self.navigate_to(u))
                sub.addAction(act)
        pos = self.btn_directory.mapToGlobal(QPoint(0, self.btn_directory.height()))
        menu.exec(pos)

    def _add_current_bookmark(self) -> None:
        url = self._current_url_str
        title = self.browser.title() or url
        if not any(u == url for _, u in self._bookmarks):
            self._bookmarks.append((title, url))
        self.status_label.setText(f"Bookmarked: {title}")

    def _manage_bookmarks(self) -> None:
        dlg = BookmarksDialog(self._bookmarks, self)
        dlg.open_requested.connect(self.navigate_to)
        dlg.exec()

    def _new_window(self) -> None:
        win = NetscapeZacBrowser()
        win.show()
        self._open_windows.append(win)

    def _open_location_dialog(self) -> None:
        dlg = QDialog(self)
        dlg.setWindowTitle("Open Page")
        dlg.setFixedWidth(400)
        lay = QVBoxLayout(dlg)
        lay.setContentsMargins(12, 12, 12, 12)
        lay.setSpacing(8)
        lay.addWidget(QLabel("Type the Internet location (URL) or choose a local file:"))
        edit = QLineEdit(self._current_url_str)
        edit.selectAll()
        lay.addWidget(edit)

        row = QHBoxLayout()
        choose_btn = QPushButton("Choose File...")

        def pick_file():
            path, _ = QFileDialog.getOpenFileName(dlg, "Open Local HTML File", "", "HTML Files (*.html *.htm);;All Files (*)")
            if path:
                edit.setText(QUrl.fromLocalFile(path).toString())

        choose_btn.clicked.connect(pick_file)
        buttons = QDialogButtonBox(QDialogButtonBox.StandardButton.Ok | QDialogButtonBox.StandardButton.Cancel)
        buttons.accepted.connect(dlg.accept)
        buttons.rejected.connect(dlg.reject)
        row.addWidget(choose_btn)
        row.addStretch(1)
        row.addWidget(buttons)
        lay.addLayout(row)

        if dlg.exec() == QDialog.DialogCode.Accepted:
            self.navigate_to(edit.text())

    def _save_page(self) -> None:
        path, _ = QFileDialog.getSaveFileName(self, "Save Page As", "page.html", "HTML Files (*.html *.htm);;All Files (*)")
        if path:
            self.browser.page().toHtml(lambda html_text: self._write_saved_html(path, html_text))

    def _write_saved_html(self, path: str, html_text: str) -> None:
        try:
            with open(path, "w", encoding="utf-8") as f:
                f.write(html_text)
            self.status_label.setText(f"Saved: {path}")
        except OSError as exc:
            QMessageBox.warning(self, "Save Error", str(exc))

    def _print_page(self) -> None:
        path, _ = QFileDialog.getSaveFileName(self, "Print Page to PDF", "netscape-page.pdf", "PDF Files (*.pdf)")
        if path:
            self.browser.page().printToPdf(path)
            self.status_label.setText(f"Printed PDF: {path}")

    def _show_find_bar(self) -> None:
        self._find_bar.show()
        self._find_input.setFocus()
        self._find_input.selectAll()

    def _hide_find_bar(self) -> None:
        self._find_bar.hide()
        self.browser.findText("")

    def _find_next(self) -> None:
        query = self._find_input.text()
        if query:
            self._last_search = query
            self.browser.findText(query)
        elif self._last_search:
            self.browser.findText(self._last_search)

    def _view_page_source(self) -> None:
        def show_source(source_text: str) -> None:
            dlg = QDialog(self)
            dlg.setWindowTitle(f"Source of: {self._current_url_str}")
            dlg.resize(680, 480)
            lay = QVBoxLayout(dlg)
            lay.setContentsMargins(8, 8, 8, 8)
            view = QPlainTextEdit()
            view.setReadOnly(True)
            view.setFont(QFont("Nimbus Mono PS", 11))
            view.setPlainText(source_text)
            lay.addWidget(view, 1)
            buttons = QDialogButtonBox(QDialogButtonBox.StandardButton.Close)
            buttons.rejected.connect(dlg.reject)
            buttons.accepted.connect(dlg.accept)
            lay.addWidget(buttons)
            dlg.exec()

        self.browser.page().toHtml(show_source)

    def _show_page_info(self) -> None:
        url = self._current_url_str
        title = self.browser.title() or "(Untitled)"
        if url.startswith("https://"):
            sec = "Encrypted connection (HTTPS) — Security padlock is locked."
        elif url.startswith("about:"):
            sec = "Built-in Netscape Navigator local document."
        else:
            sec = "Unencrypted connection (HTTP) — Information sent over this connection is not encrypted."
        QMessageBox.information(
            self,
            "Page & Security Info",
            f"Title: {title}\nLocation: {url}\n\nSecurity: {sec}",
        )

    def _show_history_dialog(self) -> None:
        dlg = QDialog(self)
        dlg.setWindowTitle("History")
        dlg.resize(480, 300)
        lay = QVBoxLayout(dlg)
        lay.setContentsMargins(10, 10, 10, 10)
        lst = QListWidget()
        for item in reversed(self.browser.history().items()):
            label = f"{item.title() or '(Untitled)'}  \u2014  {item.url().toString()}"
            row_item = QListWidgetItem(label)
            row_item.setData(Qt.ItemDataRole.UserRole, item.url().toString())
            lst.addItem(row_item)

        def open_item():
            cur = lst.currentItem()
            if cur:
                self.navigate_to(cur.data(Qt.ItemDataRole.UserRole))
                dlg.accept()

        lst.itemDoubleClicked.connect(lambda _: open_item())
        lay.addWidget(lst, 1)
        btns = QDialogButtonBox(QDialogButtonBox.StandardButton.Close)
        btns.rejected.connect(dlg.reject)
        lay.addWidget(btns)
        dlg.exec()

    def _open_preferences(self) -> None:
        settings = self.browser.page().settings()
        attr = settings.WebAttribute.AutoLoadImages
        dlg = PreferencesDialog(self._home_url, settings.testAttribute(attr), self)
        if dlg.exec() == QDialog.DialogCode.Accepted:
            new_home = dlg.home_edit.text().strip()
            if new_home:
                self._home_url = new_home
            settings.setAttribute(attr, dlg.images_chk.isChecked())

    def _show_about(self) -> None:
        AboutDialog(self).exec()


if __name__ == "__main__":
    app = QApplication(sys.argv)
    app.setApplicationName("Netscape Navigator")
    apply_zacos9_theme(app)
    window = NetscapeZacBrowser()
    window.show()
    sys.exit(app.exec())
