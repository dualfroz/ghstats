package com.dualfroz.ghstats.server.discord;

import java.util.Set;

public final class DiscordBadges {

    private static final Set<String> KNOWN = Set.of(
            "nitro", "boost", "quest", "orb", "legacy",
            "active_developer", "early_supporter",
            "hypesquad_bravery", "hypesquad_brilliance", "hypesquad_balance");

    private DiscordBadges() {
    }

    public static boolean known(String key) {
        return key != null && KNOWN.contains(key.toLowerCase());
    }

    public static String glyph(String key, int x, int y, StringBuilder defs) {
        String drawn = switch (key.toLowerCase()) {
            case "nitro" -> nitro(defs);
            case "boost" -> boost(defs);
            case "quest" -> quest(defs);
            case "orb" -> orb(defs);
            case "legacy" -> legacy(defs);
            case "active_developer" -> activeDeveloper(defs);
            case "early_supporter" -> earlySupporter(defs);
            case "hypesquad_bravery" -> hypesquad(defs, "Bravery", "#9c84ec");
            case "hypesquad_brilliance" -> hypesquad(defs, "Brilliance", "#f37b68");
            case "hypesquad_balance" -> hypesquad(defs, "Balance", "#45ddc0");
            default -> "";
        };
        if (drawn.isEmpty()) {
            return "";
        }
        return "<g transform=\"translate(" + x + "," + y + ")\">" + drawn + "</g>";
    }

    private static void linear(StringBuilder defs, String id, String from, String to) {
        defs.append("<linearGradient id=\"").append(id).append("\" x1=\"0\" y1=\"0\" x2=\"0.4\" y2=\"1\">")
                .append("<stop offset=\"0\" stop-color=\"").append(from).append("\"/>")
                .append("<stop offset=\"1\" stop-color=\"").append(to).append("\"/></linearGradient>");
    }

    private static String tile(String id) {
        return "<rect x=\"2\" y=\"2\" width=\"18\" height=\"18\" rx=\"6\" fill=\"url(#" + id + ")\"/>"
                + "<rect x=\"2.5\" y=\"2.5\" width=\"17\" height=\"17\" rx=\"5.5\" fill=\"none\""
                + " stroke=\"#ffffff\" stroke-width=\"1\" opacity=\"0.12\"/>";
    }

    private static String nitro(StringBuilder defs) {
        linear(defs, "bgNitro", "#ff8ae2", "#a94dff");
        return "<path d=\"M11 2 L19 9 L11 21 L3 9 Z\" fill=\"url(#bgNitro)\"/>"
                + "<path d=\"M11 2 L11 21 L3 9 Z\" fill=\"#ffffff\" opacity=\"0.14\"/>"
                + "<path d=\"M6.5 9 L11 4.6 L15.5 9 Z\" fill=\"#ffffff\" opacity=\"0.28\"/>"
                + "<path d=\"M11 2 L19 9 L11 21 Z\" fill=\"#000000\" opacity=\"0.07\"/>";
    }

    private static String boost(StringBuilder defs) {
        linear(defs, "bgBoost", "#f884ff", "#b45cff");
        return "<path d=\"M11 2 L16 6 L16 9 L11 21 L6 9 L6 6 Z\" fill=\"url(#bgBoost)\"/>"
                + "<line x1=\"6\" y1=\"9\" x2=\"16\" y2=\"9\" stroke=\"#ffffff\" stroke-width=\"0.9\" opacity=\"0.3\"/>"
                + "<path d=\"M11 2 L11 21 L6 9 L6 6 Z\" fill=\"#ffffff\" opacity=\"0.12\"/>";
    }

    private static String quest(StringBuilder defs) {
        linear(defs, "bgQuest", "#57d1ff", "#5865f2");
        return tile("bgQuest")
                + "<path d=\"M11 5 C11.6 9 13 10.4 17 11 C13 11.6 11.6 13 11 17"
                + " C10.4 13 9 11.6 5 11 C9 10.4 10.4 9 11 5 Z\" fill=\"#ffffff\" opacity=\"0.95\"/>"
                + "<circle cx=\"15.4\" cy=\"6.6\" r=\"1.1\" fill=\"#ffffff\" opacity=\"0.85\"/>";
    }

    private static String orb(StringBuilder defs) {
        defs.append("<radialGradient id=\"bgOrb\" cx=\"0.35\" cy=\"0.3\" r=\"0.85\">")
                .append("<stop offset=\"0\" stop-color=\"#d3bcff\"/>")
                .append("<stop offset=\"0.5\" stop-color=\"#7c5cff\"/>")
                .append("<stop offset=\"1\" stop-color=\"#4a2fd0\"/></radialGradient>");
        return "<circle cx=\"11\" cy=\"11\" r=\"9\" fill=\"url(#bgOrb)\"/>"
                + "<ellipse cx=\"8\" cy=\"7.6\" rx=\"3.2\" ry=\"2\" fill=\"#ffffff\" opacity=\"0.5\"/>"
                + "<circle cx=\"11\" cy=\"11\" r=\"9\" fill=\"none\" stroke=\"#000000\""
                + " stroke-width=\"0.8\" opacity=\"0.12\"/>";
    }

    private static String legacy(StringBuilder defs) {
        linear(defs, "bgLegacy", "#6b6f78", "#3a3c42");
        return tile("bgLegacy")
                + "<g stroke=\"#ffffff\" stroke-width=\"1.6\" stroke-linecap=\"round\">"
                + "<line x1=\"9.3\" y1=\"6\" x2=\"8\" y2=\"16\"/>"
                + "<line x1=\"14\" y1=\"6\" x2=\"12.7\" y2=\"16\"/>"
                + "<line x1=\"6.4\" y1=\"9.6\" x2=\"15.6\" y2=\"9.6\"/>"
                + "<line x1=\"6\" y1=\"12.6\" x2=\"15.2\" y2=\"12.6\"/></g>";
    }

    private static String activeDeveloper(StringBuilder defs) {
        linear(defs, "bgDev", "#4fd672", "#2f9d57");
        return tile("bgDev")
                + "<g fill=\"none\" stroke=\"#ffffff\" stroke-width=\"1.6\" stroke-linecap=\"round\""
                + " stroke-linejoin=\"round\">"
                + "<path d=\"M7 8 L10 11 L7 14\"/>"
                + "<line x1=\"11.5\" y1=\"14\" x2=\"15\" y2=\"14\"/></g>";
    }

    private static String earlySupporter(StringBuilder defs) {
        linear(defs, "bgEarly", "#7d8cff", "#c86bff");
        return tile("bgEarly")
                + "<path d=\"M11 16.2 C6 12.6 6.4 8.6 9 8.6 C10.2 8.6 11 9.6 11 9.6"
                + " C11 9.6 11.8 8.6 13 8.6 C15.6 8.6 16 12.6 11 16.2 Z\""
                + " fill=\"#ffffff\" opacity=\"0.95\"/>";
    }

    private static String hypesquad(StringBuilder defs, String variant, String color) {
        return "<path d=\"M11 3 L18 6 V11 C18 15 14.8 18 11 19 C7.2 18 4 15 4 11 V6 Z\" fill=\"" + color + "\"/>"
                + "<path d=\"M11 3 L18 6 V10 C18 12 15 13 11 13 C7 13 4 12 4 10 V6 Z\""
                + " fill=\"#ffffff\" opacity=\"0.16\"/>"
                + "<path d=\"M11 7 L13.2 11.4 L8.8 11.4 Z\" fill=\"#ffffff\" opacity=\"0.9\"/>"
                + "<path d=\"M8.8 11.4 L13.2 11.4 L11 15.4 Z\" fill=\"#ffffff\" opacity=\"0.55\"/>";
    }
}
