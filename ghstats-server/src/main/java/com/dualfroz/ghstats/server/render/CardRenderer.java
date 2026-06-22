package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;

public interface CardRenderer {

    String key();

    String render(Snapshot snapshot, RenderOptions options);
}
