<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { watch } from 'vue';

interface Props {
    modelValue?: string;
    placeholder?: string;
}

interface Emits {
    (e: 'update:modelValue', value: string): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Write something...',
});

const emit = defineEmits<Emits>();

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3, 4, 5, 6],
            },
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary underline',
            },
        }),
        Image.configure({
            HTMLAttributes: {
                class: 'max-w-full h-auto rounded',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    content: props.modelValue,
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none min-h-[200px] px-4 py-3',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        const isSame = editor.value?.getHTML() === value;
        if (!isSame && editor.value) {
            editor.value.commands.setContent(value, { emitUpdate: false });
        }
    },
);

const addLink = () => {
    const url = window.prompt('Enter URL');
    if (url && editor.value) {
        editor.value.chain().focus().setLink({ href: url }).run();
    }
};

const addImage = () => {
    const url = window.prompt('Enter image URL');
    if (url && editor.value) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
};
</script>

<template>
    <div class="rounded-md border border-input bg-background">
        <!-- Toolbar -->
        <div class="flex flex-wrap gap-1 border-b border-input p-2">
            <!-- Text Formatting -->
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleBold().run()"
                :class="{ 'bg-accent': editor?.isActive('bold') }"
                title="Bold"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleItalic().run()"
                :class="{ 'bg-accent': editor?.isActive('italic') }"
                title="Italic"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M10 4h10M4 20h10m0-16l-4 16"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleStrike().run()"
                :class="{ 'bg-accent': editor?.isActive('strike') }"
                title="Strikethrough"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 12h18M9 5h6m-6 14h6"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleCode().run()"
                :class="{ 'bg-accent': editor?.isActive('code') }"
                title="Inline Code"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                    />
                </svg>
            </Button>

            <div class="mx-1 w-px bg-border"></div>

            <!-- Headings -->
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="
                    editor?.chain().focus().toggleHeading({ level: 1 }).run()
                "
                :class="{
                    'bg-accent': editor?.isActive('heading', { level: 1 }),
                }"
                title="Heading 1"
            >
                H1
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="
                    editor?.chain().focus().toggleHeading({ level: 2 }).run()
                "
                :class="{
                    'bg-accent': editor?.isActive('heading', { level: 2 }),
                }"
                title="Heading 2"
            >
                H2
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="
                    editor?.chain().focus().toggleHeading({ level: 3 }).run()
                "
                :class="{
                    'bg-accent': editor?.isActive('heading', { level: 3 }),
                }"
                title="Heading 3"
            >
                H3
            </Button>

            <div class="mx-1 w-px bg-border"></div>

            <!-- Lists -->
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleBulletList().run()"
                :class="{ 'bg-accent': editor?.isActive('bulletList') }"
                title="Bullet List"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleOrderedList().run()"
                :class="{ 'bg-accent': editor?.isActive('orderedList') }"
                title="Numbered List"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 4h1v4H3m0 4h1v4H3m0 4h1v4H3m4-16h14M7 12h14M7 20h14"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleBlockquote().run()"
                :class="{ 'bg-accent': editor?.isActive('blockquote') }"
                title="Blockquote"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
                :class="{ 'bg-accent': editor?.isActive('codeBlock') }"
                title="Code Block"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </Button>

            <div class="mx-1 w-px bg-border"></div>

            <!-- Links & Images -->
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="addLink"
                :class="{ 'bg-accent': editor?.isActive('link') }"
                title="Add Link"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="addImage"
                title="Add Image"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </Button>

            <div class="mx-1 w-px bg-border"></div>

            <!-- Undo & Redo -->
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().undo().run()"
                :disabled="!editor?.can().undo()"
                title="Undo"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"
                    />
                </svg>
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                @click="editor?.chain().focus().redo().run()"
                :disabled="!editor?.can().redo()"
                title="Redo"
            >
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"
                    />
                </svg>
            </Button>
        </div>

        <!-- Editor Content -->
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
/* Tiptap Editor Styles */
.ProseMirror {
    outline: none;
}

.ProseMirror p.is-editor-empty:first-child::before {
    color: hsl(var(--muted-foreground));
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

.ProseMirror h1 {
    font-size: 2em;
    font-weight: bold;
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.ProseMirror h2 {
    font-size: 1.5em;
    font-weight: bold;
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.ProseMirror h3 {
    font-size: 1.25em;
    font-weight: bold;
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.ProseMirror ul,
.ProseMirror ol {
    padding-left: 1.5em;
    margin: 0.5em 0;
}

.ProseMirror blockquote {
    border-left: 3px solid hsl(var(--border));
    padding-left: 1em;
    margin: 1em 0;
    font-style: italic;
}

.ProseMirror code {
    background-color: hsl(var(--muted));
    padding: 0.2em 0.4em;
    border-radius: 0.25em;
    font-family: monospace;
    font-size: 0.9em;
}

.ProseMirror pre {
    background-color: hsl(var(--muted));
    padding: 1em;
    border-radius: 0.5em;
    overflow-x: auto;
    margin: 1em 0;
}

.ProseMirror pre code {
    background: none;
    padding: 0;
}

.ProseMirror img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5em;
}

.ProseMirror a {
    color: hsl(var(--primary));
    text-decoration: underline;
}
</style>
