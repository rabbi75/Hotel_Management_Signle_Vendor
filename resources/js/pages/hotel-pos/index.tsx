import { Head } from '@inertiajs/react';

import { PageHeader } from '@/components/app-shell/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type RestaurantRow = {
    id: number;
    name: string;
    code: string | null;
    hotel: string | null;
    is_active: boolean;
    orders_count: number;
};

type Props = {
    restaurants: RestaurantRow[];
};

export default function HotelPosIndex({ restaurants }: Props) {
    return (
        <AppLayout>
            <Head title="Restaurants" />

            <PageHeader
                title="Restaurants & POS"
                description="Restaurant outlets linked to your properties. Full menu, table service, and folio posting will be added in a future release."
            />

            {restaurants.length === 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>No restaurants yet</CardTitle>
                        <CardDescription>
                            POS architecture is in place. Create restaurant outlets when you are ready to enable food & beverage operations.
                        </CardDescription>
                    </CardHeader>
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {restaurants.map((restaurant) => (
                        <Card key={restaurant.id}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <CardTitle>{restaurant.name}</CardTitle>
                                        <CardDescription>{restaurant.hotel ?? 'Unassigned property'}</CardDescription>
                                    </div>
                                    <Badge variant={restaurant.is_active ? 'default' : 'secondary'}>
                                        {restaurant.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="text-sm text-muted-foreground">
                                {restaurant.code ? <p>Code: {restaurant.code}</p> : null}
                                <p>{restaurant.orders_count} POS order stub(s)</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
