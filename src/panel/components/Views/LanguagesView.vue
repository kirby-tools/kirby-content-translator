<script lang="ts">
import type { DefineComponent } from "vue";
import TranslationCoverageView from "./TranslationCoverageView.vue";

/** The members of Kirby's view that the template reads. */
type KirbyLanguagesView = DefineComponent<
  { buttons: unknown[]; languages: unknown[] },
  object,
  object,
  { primaryLanguage: () => unknown[]; secondaryLanguages: () => unknown[] }
>;

// TODO: Drop this copy of Kirby's template once `k-languages-view` takes a slot
// for the coverage (getkirby/kirby#8704).
export default {
  components: { TranslationCoverageView },
  // Resolved by Kirby's plugin loader.
  extends: "k-languages-view" as unknown as KirbyLanguagesView,
};
</script>

<template>
  <k-panel-inside class="k-languages-view">
    <k-header>
      {{ $t("view.languages") }}

      <template #buttons>
        <k-view-buttons :buttons="buttons" />
      </template>
    </k-header>

    <template v-if="languages.length > 0">
      <k-section :headline="$t('languages.default')">
        <k-collection :items="primaryLanguage" />
      </k-section>

      <k-section :headline="$t('languages.secondary')">
        <k-collection
          v-if="secondaryLanguages.length"
          :items="secondaryLanguages"
        />
        <k-empty
          v-else
          icon="translate"
          :disabled="!$panel.permissions.languages.create"
          @click="$dialog('languages/create')"
        >
          {{ $t("languages.secondary.empty") }}
        </k-empty>
      </k-section>
    </template>

    <template v-else-if="languages.length === 0">
      <k-empty
        icon="translate"
        :disabled="!$panel.permissions.languages.create"
        @click="$dialog('languages/create')"
      >
        {{ $t("languages.empty") }}
      </k-empty>
    </template>

    <TranslationCoverageView />
  </k-panel-inside>
</template>
