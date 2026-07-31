import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { FamiliarManifest } from '@/types/familiars';
import type { PrototypeQuest } from '../types';

interface PrototypeQuestListProps {
    manifest: FamiliarManifest;
    quests: PrototypeQuest[];
    completed: Set<string>;
    onToggle: (quest: PrototypeQuest) => void;
}

export function PrototypeQuestList({
    manifest,
    quests,
    completed,
    onToggle,
}: PrototypeQuestListProps) {
    return (
        <section
            className="space-y-3"
            aria-labelledby="prototype-quests-heading"
        >
            <div>
                <h2
                    id="prototype-quests-heading"
                    className="text-lg font-semibold"
                >
                    Prototype quests
                </h2>
                <p className="text-sm text-muted-foreground">
                    Completion is stored only in this browser session.
                </p>
            </div>
            <ul className="space-y-2">
                {quests.map((quest) => {
                    const isComplete = completed.has(quest.key);
                    const icon = manifest.quests[quest.key].icon;

                    return (
                        <li
                            key={quest.key}
                            className="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <img
                                alt=""
                                aria-hidden="true"
                                className="size-12 shrink-0 [image-rendering:pixelated]"
                                src={icon.src}
                            />
                            <span
                                className={`min-w-0 flex-1 text-sm font-medium ${isComplete ? 'text-muted-foreground line-through' : ''}`}
                            >
                                {quest.label}
                            </span>
                            <Button
                                aria-pressed={isComplete}
                                size="sm"
                                variant={isComplete ? 'secondary' : 'outline'}
                                onClick={() => onToggle(quest)}
                            >
                                {isComplete ? (
                                    <>
                                        <Check /> Complete
                                    </>
                                ) : (
                                    'Complete'
                                )}
                            </Button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
