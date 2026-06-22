package com.dualfroz.ghstats.server;

import com.dualfroz.ghstats.server.render.CardRenderer;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import org.springframework.stereotype.Component;

@Component
public class CardCatalog {

    private final Map<String, CardRenderer> renderers = new LinkedHashMap<>();

    public CardCatalog(List<CardRenderer> cardRenderers) {
        for (CardRenderer renderer : cardRenderers) {
            renderers.put(renderer.key(), renderer);
        }
    }

    public CardRenderer get(String key) {
        return renderers.get(key);
    }

    public Set<String> keys() {
        return renderers.keySet();
    }
}
